<?php

namespace App\Http\Modules\Cortes\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Request\RequestUploadUrlRequest;
use App\Http\Modules\Cortes\Request\ResubmitCorteRequest;
use App\Http\Modules\Cortes\Request\ReviewCorteRequest;
use App\Http\Modules\Cortes\Request\StoreCorteInvoiceRequest;
use App\Http\Modules\Cortes\Request\StoreCorteRequest;
use App\Http\Modules\Cortes\Service\CorteInvoiceService;
use App\Http\Modules\Cortes\Service\CorteService;
use App\Http\Modules\Cortes\Service\CorteStorageService;
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
     * Cargue de factura (o nota crédito / factura de reemplazo) a un corte con las tres aprobaciones.
     */
    public function storeInvoice(StoreCorteInvoiceRequest $request, Corte $corte): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->create($corte, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $invoice->documentTypeLabel() . ' cargada. Se notificó para su registro en Multifox.',
                'data' => $this->corteService->getCorte($corte->fresh(), $request->user()),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('cargar la factura', $e);
        }
    }

    /**
     * Confirmación de que la factura quedó cargada en Multifox: pasa a cuentas por pagar.
     */
    public function confirmMultifox(Request $request, CorteInvoice $invoice): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->confirmMultifox($invoice, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Carga en Multifox confirmada. La factura pasó a cuentas por pagar.',
                'data' => $this->corteService->getCorte($invoice->corte, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('confirmar la carga en Multifox', $e);
        }
    }

    public function downloadInvoice(Request $request, CorteInvoice $invoice): JsonResponse
    {
        if (!$this->corteService->canView($invoice->corte, $request->user())) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'download_url' => $this->storage->fileDownloadUrl($invoice->file_path, $invoice->file_name),
                    'name' => $invoice->file_name,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }
}
