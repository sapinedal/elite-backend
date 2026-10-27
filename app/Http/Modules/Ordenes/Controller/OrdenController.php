<?php

namespace App\Http\Modules\Ordenes\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Request\RequestOrdenUploadUrlRequest;
use App\Http\Modules\Ordenes\Request\ReviewOrdenRequest;
use App\Http\Modules\Ordenes\Request\StoreOrdenDocumentRequest;
use App\Http\Modules\Ordenes\Request\StoreOrdenRequest;
use App\Http\Modules\Ordenes\Service\OrdenService;
use App\Http\Modules\Ordenes\Service\OrdenStorageService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class OrdenController extends Controller
{
    public function __construct(
        protected OrdenService $ordenService,
        protected OrdenStorageService $storage,
    ) {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta orden.'], 403);
    }

    protected function serverError(string $action, Throwable $e): JsonResponse
    {
        report($e);

        return response()->json(['status' => 'error', 'message' => "Error al {$action}: " . $e->getMessage()], 500);
    }

    public function catalogs(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->ordenService->catalogs($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope', OrdenService::SCOPE_MINE);
        $filters = $request->only(['search', 'status', 'project_id', 'per_page']);

        return response()->json([
            'status' => 'success',
            'data' => $this->ordenService->listOrdenes($request->user(), $scope, $filters),
            'counts' => $this->ordenService->counts($request->user()),
        ]);
    }

    public function show(Request $request, Orden $orden): JsonResponse
    {
        if (!$this->ordenService->canView($orden, $request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->ordenService->getOrden($orden, $request->user())]);
    }

    /**
     * Paso 1 de la carga de un documento: URL firmada para subir el archivo directo a S3.
     */
    public function uploadUrl(RequestOrdenUploadUrlRequest $request): JsonResponse
    {
        if (OrdenWorkflow::stepsFor($request->user()) === []) {
            return response()->json(['status' => 'error', 'message' => 'No tienes documentos de órdenes por cargar.'], 403);
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

    public function store(StoreOrdenRequest $request): JsonResponse
    {
        try {
            $orden = $this->ordenService->createOrden($request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => "Orden {$orden->code} registrada. Se notificó al Director de Obra para su aprobación.",
                'data' => $this->ordenService->getOrden($orden, $request->user()),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('registrar la orden', $e);
        }
    }

    public function review(ReviewOrdenRequest $request, Orden $orden): JsonResponse
    {
        if (!$this->ordenService->canView($orden, $request->user())) {
            return $this->forbidden();
        }

        try {
            $decision = $request->validated('decision');
            $orden = $this->ordenService->review($orden, $decision, $request->validated('comment'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $decision === 'aprobar' ? 'Orden aprobada.' : 'Orden devuelta al residente.',
                'data' => $this->ordenService->getOrden($orden, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('registrar la aprobación', $e);
        }
    }

    public function resubmit(StoreOrdenRequest $request, Orden $orden): JsonResponse
    {
        try {
            $orden = $this->ordenService->resubmit($orden, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Orden reenviada. Se notificó al Director de Obra para su aprobación.',
                'data' => $this->ordenService->getOrden($orden, $request->user()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('reenviar la orden', $e);
        }
    }

    /**
     * Cargue del documento pendiente: orden de compra del ERP, factura del proveedor o entrada de almacén.
     */
    public function storeDocument(StoreOrdenDocumentRequest $request, Orden $orden): JsonResponse
    {
        try {
            $document = $this->ordenService->uploadDocument($orden, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $document->stepLabel() . ' cargada.',
                'data' => $this->ordenService->getOrden($orden->fresh(), $request->user()),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->serverError('cargar el documento', $e);
        }
    }

    public function downloadDocument(Request $request, OrdenDocument $document): JsonResponse
    {
        if (!$this->ordenService->canView($document->orden, $request->user())) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => ['download_url' => $this->ordenService->documentDownloadUrl($document), 'name' => $document->file_name],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }
}
