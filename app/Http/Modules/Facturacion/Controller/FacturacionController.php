<?php

namespace App\Http\Modules\Facturacion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Cortes\Request\RequestUploadUrlRequest;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Models\FacturacionInvoiceSupport;
use App\Http\Modules\Facturacion\Request\AssociateFacturacionInvoiceRequest;
use App\Http\Modules\Facturacion\Request\SaveFacturacionInvoiceRequest;
use App\Http\Modules\Facturacion\Service\FacturacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class FacturacionController extends Controller
{
    public function __construct(protected FacturacionService $service)
    {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta factura.'], 403);
    }

    /**
     * Ejecuta la acción y traduce los errores de negocio (422) y los inesperados (500).
     */
    protected function attempt(string $action, callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 'error', 'message' => "Error al {$action}: " . $e->getMessage()], 500);
        }
    }

    public function catalogs(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->catalogs($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list(
            $request->user(),
            $request->query('scope', FacturacionService::SCOPE_PENDING),
            $request->only(['period', 'search', 'per_page'])
        );

        return response()->json(['status' => 'success', 'data' => $result['page'], 'counts' => $result['counts']]);
    }

    public function show(Request $request, FacturacionInvoice $invoice): JsonResponse
    {
        if (!$this->service->canView($invoice, $request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->service->get($invoice, $request->user())]);
    }

    /**
     * Paso 1 de la carga (factura o soporte): URL firmada para subir el archivo directo a S3.
     */
    public function uploadUrl(RequestUploadUrlRequest $request): JsonResponse
    {
        return $this->attempt('preparar la carga del archivo', fn () => response()->json([
            'status' => 'success',
            'data' => $this->service->requestUpload(
                $request->validated('file_name'),
                (int) $request->validated('file_size'),
                $request->validated('mime_type'),
                $request->user()
            ),
        ]));
    }

    public function store(SaveFacturacionInvoiceRequest $request): JsonResponse
    {
        return $this->attempt('cargar la factura', function () use ($request) {
            $invoice = $this->service->create($request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Factura cargada. Queda pendiente de que obra la asocie.',
                'data' => $this->service->get($invoice, $request->user()),
            ], 201);
        });
    }

    public function update(SaveFacturacionInvoiceRequest $request, FacturacionInvoice $invoice): JsonResponse
    {
        return $this->attempt('actualizar la factura', function () use ($request, $invoice) {
            $invoice = $this->service->update($invoice, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Factura actualizada.',
                'data' => $this->service->get($invoice, $request->user()),
            ]);
        });
    }

    public function destroy(Request $request, FacturacionInvoice $invoice): JsonResponse
    {
        return $this->attempt('eliminar la factura', function () use ($request, $invoice) {
            $this->service->delete($invoice, $request->user());

            return response()->json(['status' => 'success', 'message' => 'Factura eliminada.']);
        });
    }

    public function targets(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->targets($request->user())]);
    }

    public function associate(AssociateFacturacionInvoiceRequest $request, FacturacionInvoice $invoice): JsonResponse
    {
        return $this->attempt('asociar la factura', function () use ($request, $invoice) {
            $invoice = $this->service->associate($invoice, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $invoice->target_type === FacturacionInvoice::TARGET_CORTE
                    ? 'Factura asociada al corte. Se notificó para su registro en Multifox.'
                    : 'Factura asociada a la orden. Pasa a la entrada de almacén.',
                'data' => $this->service->get($invoice, $request->user()),
            ]);
        });
    }

    public function panel(Request $request): JsonResponse
    {
        return $this->attempt('consultar el panel', fn () => response()->json([
            'status' => 'success',
            'data' => $this->service->panel($request->user(), $request->query('period')),
        ]));
    }

    public function download(Request $request, FacturacionInvoice $invoice): JsonResponse
    {
        if (!$this->service->canView($invoice, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('descargar la factura', fn () => response()->json([
            'status' => 'success',
            'data' => ['download_url' => $this->service->downloadUrl($invoice), 'name' => $invoice->file_name],
        ]));
    }

    public function downloadSupport(Request $request, FacturacionInvoiceSupport $support): JsonResponse
    {
        if (!$this->service->canView($support->invoice, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('descargar el soporte', fn () => response()->json([
            'status' => 'success',
            'data' => ['download_url' => $this->service->supportDownloadUrl($support), 'name' => $support->file_name],
        ]));
    }
}
