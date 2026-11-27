<?php

namespace App\Http\Modules\Anticipos\Controller;

use App\Http\Modules\Pagos\Request\AccountingDecisionRequest;
use App\Http\Controllers\Controller;
use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Request\RegisterMultifoxRequest;
use App\Http\Modules\Anticipos\Request\ResubmitAnticipoRequest;
use App\Http\Modules\Anticipos\Request\ReviewAnticipoRequest;
use App\Http\Modules\Anticipos\Request\StoreAnticipoRequest;
use App\Http\Modules\Anticipos\Service\AnticipoService;
use App\Http\Modules\Cortes\Request\RequestUploadUrlRequest;
use App\Services\RegistroDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AnticipoController extends Controller
{
    public function __construct(protected AnticipoService $service)
    {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso a este anticipo.'], 403);
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
        return response()->json([
            'status' => 'success',
            'data' => $this->service->listAnticipos(
                $request->user(),
                $request->query('scope', AnticipoService::SCOPE_MINE),
                $request->only(['search', 'status', 'project_id', 'per_page'])
            ),
            'counts' => $this->service->counts($request->user()),
        ]);
    }

    public function show(Request $request, Anticipo $anticipo): JsonResponse
    {
        if (!$this->service->canView($anticipo, $request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->service->getAnticipo($anticipo, $request->user())]);
    }

    /**
     * Paso 1 de la carga (cuenta de cobro o soporte de Multifox): URL firmada para subir el archivo directo a S3.
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

    public function store(StoreAnticipoRequest $request): JsonResponse
    {
        return $this->attempt('radicar el anticipo', function () use ($request) {
            $anticipo = $this->service->createAnticipo($request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => "Anticipo {$anticipo->code} radicado. Se notificó al Director de Obra.",
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ], 201);
        });
    }

    public function review(ReviewAnticipoRequest $request, Anticipo $anticipo): JsonResponse
    {
        if (!$this->service->canView($anticipo, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('registrar la aprobación', function () use ($request, $anticipo) {
            $decision = $request->validated('decision');
            $anticipo = $this->service->review($anticipo, $decision, $request->validated('comment'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $decision === 'aprobar' ? 'Anticipo aprobado.' : 'Anticipo devuelto al residente.',
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ]);
        });
    }

    public function resubmit(ResubmitAnticipoRequest $request, Anticipo $anticipo): JsonResponse
    {
        return $this->attempt('reenviar el anticipo', function () use ($request, $anticipo) {
            $anticipo = $this->service->resubmit($anticipo, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Anticipo reenviado. Se notificó al Director de Obra.',
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ]);
        });
    }

    /**
     * El residente adjunta el contrato u orden de compra del anticipo aprobado: pasa a Contabilidad (Multifox).
     */
    public function uploadSupport(Request $request, Anticipo $anticipo): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => ['required', 'uuid'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['upload_token.required' => 'Adjunta el contrato u orden de compra.'], ['upload_token' => 'contrato u orden de compra', 'comment' => 'comentario']);

        return $this->attempt('adjuntar el contrato u orden de compra', function () use ($request, $anticipo, $data) {
            $anticipo = $this->service->uploadSupport($anticipo, $data, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato / orden de compra adjunto. Se notificó a Contabilidad para generar el anticipo en Multifox.',
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ]);
        });
    }

    public function registerMultifox(RegisterMultifoxRequest $request, Anticipo $anticipo): JsonResponse
    {
        return $this->attempt('cargar el anticipo de Multifox', function () use ($request, $anticipo) {
            $anticipo = $this->service->registerMultifox($anticipo, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Anticipo cargado. Pasó a la aprobación de Contabilidad.',
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ]);
        });
    }

    public function reviewAccounting(AccountingDecisionRequest $request, Anticipo $anticipo): JsonResponse
    {
        return $this->attempt('registrar la aprobación de Contabilidad', function () use ($request, $anticipo) {
            $decision = $request->validated('decision');
            $anticipo = $this->service->reviewAccounting($anticipo, $decision, $request->validated('comment'), $request->user(), $request->validated('amount') !== null ? (float) $request->validated('amount') : null);

            return response()->json([
                'status' => 'success',
                'message' => $decision === 'aprobar' ? 'Anticipo aprobado. Pasó al cuadro de pagos.' : 'Anticipo devuelto a Multifox.',
                'data' => $this->service->getAnticipo($anticipo, $request->user()),
            ]);
        });
    }

    public function download(Request $request, Anticipo $anticipo): JsonResponse
    {
        if (!$this->service->canView($anticipo, $request->user())) {
            return $this->forbidden();
        }

        $multifox = $request->query('file') === 'multifox';
        $support = $request->query('file') === 'soporte';

        return $this->attempt('descargar el archivo', fn () => response()->json([
            'status' => 'success',
            'data' => [
                'download_url' => $this->service->downloadUrl($anticipo, $multifox, $support),
                'name' => match (true) {
                    $support => $anticipo->support_file_name,
                    $multifox => $anticipo->multifox_file_name,
                    default => $anticipo->file_name,
                },
            ],
        ]));
    }

    /**
     * Elimina un anticipo cargado por error (en cualquier estado). Exige anticipos.eliminar y motivo.
     */
    public function destroy(Request $request, Anticipo $anticipo, RegistroDeletionService $deletion): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        return $this->attempt('eliminar el anticipo', function () use ($request, $anticipo, $deletion) {
            $deletion->deleteAnticipo($anticipo, (string) $request->input('reason'), $request->user());

            return response()->json(['status' => 'success', 'message' => 'Anticipo eliminado. Se reversaron su cuenta por pagar y su factura del mes.']);
        });
    }
}
