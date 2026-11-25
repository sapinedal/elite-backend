<?php

namespace App\Http\Modules\Cortes\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Request\ConfirmMultifoxRequest;
use App\Http\Modules\Pagos\Request\AccountingDecisionRequest;
use App\Http\Modules\Cortes\Request\RequestUploadUrlRequest;
use App\Http\Modules\Cortes\Request\ResubmitCorteRequest;
use App\Http\Modules\Cortes\Request\ReviewCorteRequest;
use App\Http\Modules\Cortes\Request\StoreCorteRequest;
use App\Http\Modules\Cortes\Service\CorteInvoiceService;
use App\Http\Modules\Cortes\Service\CorteService;
use App\Http\Modules\Cortes\Service\CorteStorageService;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Services\RegistroDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CorteController extends Controller
{
    public function __construct(
        protected CorteService $corteService,
        protected CorteStorageService $storage,
        protected CorteInvoiceService $invoiceService,
    ) {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso a este corte.'], 403);
    }

    protected function serverError(string $action, Throwable $e): JsonResponse
    {
        report($e);

        return response()->json(['status' => 'error', 'message' => "Error al {$action}: " . $e->getMessage()], 500);
    }

    public function catalogs(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->corteService->catalogs($request->user()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope', CorteService::SCOPE_MINE);
        $filters = $request->only(['search', 'status', 'project_id', 'per_page']);

        return response()->json([
            'status' => 'success',
            'data' => $this->corteService->listCortes($request->user(), $scope, $filters),
            'counts' => $this->corteService->counts($request->user()),
        ]);
    }

    public function show(Request $request, Corte $corte): JsonResponse
    {
        if (!$this->corteService->canView($corte, $request->user())) {
            return $this->forbidden();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->corteService->getCorte($corte, $request->user()),
        ]);
    }

    /**
     * Paso 1 de la carga: URL firmada para que el navegador suba el archivo directo a S3.
     */
    public function uploadUrl(RequestUploadUrlRequest $request): JsonResponse
    {
        // Suben archivos quien radica cortes y quien carga el soporte de Multifox (Paula, por cargo o permiso)
        $user = $request->user();
        if (!$user->can('cortes.crear') && !CorteWorkflow::canConfirmMultifox($user)) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->storage->requestUpload(
                    $request->validated('file_name'),
                    (int) $request->validated('file_size'),
                    $request->validated('mime_type'),
                    $request->user()
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('preparar la carga del archivo', $e);
        }
    }

    public function store(StoreCorteRequest $request): JsonResponse
    {
        try {
            $corte = $this->corteService->createCorte($request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Corte radicado. Se notificó al Interventor para su revisión.',
                'data' => $this->corteService->getCorte($corte, $request->user()),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('radicar el corte', $e);
        }
    }

    public function review(ReviewCorteRequest $request, Corte $corte): JsonResponse
    {
        if (!$this->corteService->canView($corte, $request->user())) {
            return $this->forbidden();
        }

        try {
            $decision = $request->validated('decision');
            $corte = $this->corteService->review($corte, $decision, $request->validated('comment'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $decision === 'aprobar' ? 'Corte aprobado.' : 'Corte devuelto al residente.',
                'data' => $this->corteService->getCorte($corte, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('registrar la revisión', $e);
        }
    }

    public function resubmit(ResubmitCorteRequest $request, Corte $corte): JsonResponse
    {
        try {
            $corte = $this->corteService->resubmit($corte, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Corte reenviado. Se notificó al Interventor para su revisión.',
                'data' => $this->corteService->getCorte($corte, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('reenviar el corte', $e);
        }
    }

    public function download(Request $request, Corte $corte): JsonResponse
    {
        if (!$this->corteService->canView($corte, $request->user())) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => ['download_url' => $this->corteService->downloadUrl($corte), 'name' => $corte->file_name],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Confirmación de que la factura quedó cargada en Multifox: pasa a cuentas por pagar.
     */
    public function confirmMultifox(ConfirmMultifoxRequest $request, CorteInvoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->confirmMultifox($invoice, $request->validated('upload_token'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Carga en Multifox confirmada. La factura pasó a la aprobación de Contabilidad.',
                'data' => $this->corteService->getCorte($invoice->corte, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('confirmar la carga en Multifox', $e);
        }
    }

    /**
     * Contabilidad aprueba la factura cargada en Multifox (pasa a cuentas por pagar) o la devuelve a Multifox.
     */
    public function reviewAccounting(AccountingDecisionRequest $request, CorteInvoice $invoice): JsonResponse
    {
        try {
            $decision = $request->validated('decision');
            $invoice = $this->invoiceService->reviewAccounting($invoice, $decision, $request->validated('comment'), $request->user(), $request->validated('amount') !== null ? (float) $request->validated('amount') : null);

            return response()->json([
                'status' => 'success',
                'message' => $decision === 'aprobar'
                    ? 'Factura aprobada por Contabilidad. Pasó a cuentas por pagar.'
                    : 'Factura devuelta a Multifox. Se notificó a quien la carga.',
                'data' => $this->corteService->getCorte($invoice->corte, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('registrar la aprobación de Contabilidad', $e);
        }
    }

    public function downloadInvoice(Request $request, CorteInvoice $invoice): JsonResponse
    {
        if (!$this->corteService->canView($invoice->corte, $request->user())) {
            return $this->forbidden();
        }

        // ?multifox=1 descarga el soporte de Multifox que cargó Paula en vez de la factura
        $multifox = $request->boolean('multifox');
        if ($multifox && !$invoice->multifox_file_path) {
            return response()->json(['status' => 'error', 'message' => 'Esta factura no tiene soporte de Multifox.'], 404);
        }
        $path = $multifox ? $invoice->multifox_file_path : $invoice->file_path;
        $name = $multifox ? $invoice->multifox_file_name : $invoice->file_name;

        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'download_url' => $this->storage->fileDownloadUrl($path, $name),
                    'name' => $name,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Elimina un corte cargado por error (en cualquier estado): anula sus cuentas por pagar sin pagos y libera
     * sus facturas del mes. Exige cortes.eliminar y motivo.
     */
    public function destroy(Request $request, Corte $corte, RegistroDeletionService $deletion): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        try {
            $deletion->deleteCorte($corte, (string) $request->input('reason'), $request->user());

            return response()->json(['status' => 'success', 'message' => 'Corte eliminado. Se reversaron sus cuentas por pagar y facturas del mes.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('eliminar el corte', $e);
        }
    }

    /**
     * Elimina una factura de un corte (y reversa su cuenta por pagar). Exige cortes.eliminar y motivo.
     */
    public function destroyInvoice(Request $request, CorteInvoice $invoice, RegistroDeletionService $deletion): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        try {
            $corte = $deletion->deleteCorteInvoice($invoice, (string) $request->input('reason'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Factura eliminada del corte. La factura del mes volvió a quedar pendiente.',
                'data' => $this->corteService->getCorte($corte->fresh(), $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('eliminar la factura', $e);
        }
    }
}
