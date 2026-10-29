<?php

namespace App\Http\Modules\CuentasCobro\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Cortes\Request\RequestUploadUrlRequest;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\CuentasCobro\Request\MultifoxDecisionRequest;
use App\Http\Modules\CuentasCobro\Request\RegisterCuentaMultifoxRequest;
use App\Http\Modules\CuentasCobro\Request\ResubmitCuentaCobroRequest;
use App\Http\Modules\CuentasCobro\Request\ReviewCuentaCobroRequest;
use App\Http\Modules\CuentasCobro\Request\SaveCuentaCobroTipoRequest;
use App\Http\Modules\CuentasCobro\Request\StoreCuentaCobroRequest;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroService;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroTipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CuentaCobroController extends Controller
{
    public function __construct(
        protected CuentaCobroService $service,
        protected CuentaCobroTipoService $tipos,
    ) {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta cuenta de cobro.'], 403);
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

    protected function respond(CuentaCobro $cuenta, Request $request, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $this->service->getCuenta($cuenta, $request->user()),
        ], $status);
    }

    public function catalogs(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->catalogs($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->service->listCuentas(
                $request->user(),
                $request->query('scope', CuentaCobroService::SCOPE_MINE),
                $request->only(['search', 'status', 'project_id', 'tipo_id', 'per_page'])
            ),
            'counts' => $this->service->counts($request->user()),
        ]);
    }

    public function show(Request $request, CuentaCobro $cuenta): JsonResponse
    {
        if (!$this->service->canView($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->service->getCuenta($cuenta, $request->user())]);
    }

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

    public function store(StoreCuentaCobroRequest $request): JsonResponse
    {
        return $this->attempt('radicar la cuenta de cobro', function () use ($request) {
            $cuenta = $this->service->createCuenta($request->validated(), $request->user());

            return $this->respond($cuenta, $request, "Cuenta de cobro {$cuenta->code} radicada. Se notificó al Director de Obra.", 201);
        });
    }

    public function review(ReviewCuentaCobroRequest $request, CuentaCobro $cuenta): JsonResponse
    {
        if (!$this->service->canView($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('registrar la aprobación', function () use ($request, $cuenta) {
            $decision = $request->validated('decision');
            $cuenta = $this->service->review($cuenta, $decision, $request->validated('comment'), $request->user());

            return $this->respond($cuenta, $request, $decision === 'aprobar' ? 'Cuenta de cobro aprobada.' : 'Cuenta de cobro devuelta.');
        });
    }

    public function resubmit(ResubmitCuentaCobroRequest $request, CuentaCobro $cuenta): JsonResponse
    {
        return $this->attempt('reenviar la cuenta de cobro', function () use ($request, $cuenta) {
            $cuenta = $this->service->resubmit($cuenta, $request->validated(), $request->user());

            return $this->respond($cuenta, $request, 'Cuenta de cobro reenviada. Se notificó al Director de Obra.');
        });
    }

    public function decideMultifox(MultifoxDecisionRequest $request, CuentaCobro $cuenta): JsonResponse
    {
        return $this->attempt('registrar la decisión de Multifox', function () use ($request, $cuenta) {
            $requires = (bool) $request->validated('requires_multifox');
            $cuenta = $this->service->decideMultifox($cuenta, $requires, $request->validated('comment'), $request->user());

            return $this->respond($cuenta, $request, $requires
                ? 'Marcada como pendiente de cargue en Multifox.'
                : 'No requiere Multifox: pasó al cuadro de pagos.');
        });
    }

    public function registerMultifox(RegisterCuentaMultifoxRequest $request, CuentaCobro $cuenta): JsonResponse
    {
        return $this->attempt('cargar en Multifox', function () use ($request, $cuenta) {
            $cuenta = $this->service->registerMultifox($cuenta, $request->validated(), $request->user());

            return $this->respond($cuenta, $request, 'Cargada en Multifox: pasó al cuadro de pagos.');
        });
    }

    public function download(Request $request, CuentaCobro $cuenta): JsonResponse
    {
        if (!$this->service->canView($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('descargar el archivo', fn () => response()->json([
            'status' => 'success',
            'data' => ['download_url' => $this->service->downloadUrl($cuenta, (string) $request->query('file', 'cuenta'), $request->integer('adjunto') ?: null)],
        ]));
    }

    // --- Tipos de cuenta de cobro ---

    public function tipos(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->tipos->list()]);
    }

    public function storeTipo(SaveCuentaCobroTipoRequest $request): JsonResponse
    {
        $tipo = $this->tipos->save($request->validated());

        return response()->json(['status' => 'success', 'message' => 'Tipo creado.', 'data' => $this->tipos->present($tipo)], 201);
    }

    public function updateTipo(SaveCuentaCobroTipoRequest $request, CuentaCobroTipo $tipo): JsonResponse
    {
        $tipo = $this->tipos->save($request->validated(), $tipo);

        return response()->json(['status' => 'success', 'message' => 'Tipo actualizado.', 'data' => $this->tipos->present($tipo)]);
    }

    public function destroyTipo(CuentaCobroTipo $tipo): JsonResponse
    {
        $deleted = $this->tipos->delete($tipo);

        return response()->json([
            'status' => 'success',
            'message' => $deleted ? 'Tipo eliminado.' : 'El tipo tiene cuentas de cobro registradas: se desactivó en lugar de eliminarse.',
            'deleted' => $deleted,
        ]);
    }
}
