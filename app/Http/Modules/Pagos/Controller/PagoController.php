<?php

namespace App\Http\Modules\Pagos\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Pagos\Models\PagoMovimiento;
use App\Http\Modules\Pagos\Request\PayPagoCuentaRequest;
use App\Http\Modules\Pagos\Request\PrioritizePagosRequest;
use App\Http\Modules\Pagos\Request\StorePagoCuentaRequest;
use App\Http\Modules\Pagos\Service\PagoExportService;
use App\Http\Modules\Pagos\Service\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use InvalidArgumentException;
use Throwable;

class PagoController extends Controller
{
    public function __construct(protected PagoService $pagos)
    {
    }

    protected function forbidden(string $message = 'No tienes acceso a cuentas por pagar.'): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], 403);
    }

    /**
     * Ejecuta una acción y traduce las validaciones de negocio a 422.
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
        if (!$this->pagos->canView($request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->pagos->catalogs($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->pagos->canView($request->user())) {
            return $this->forbidden();
        }

        $scope = $request->query('scope', PagoService::SCOPE_TO_PRIORITIZE);
        $filters = $request->only(['search', 'status', 'project_id', 'frente', 'priority', 'source_type', 'scheduled_for', 'per_page']);
        $result = $this->pagos->listCuentas($request->user(), $scope, $filters);

        return response()->json([
            'status' => 'success',
            'data' => $result['page'],
            'total_balance' => $result['total_balance'],
            'counts' => $this->pagos->counts($request->user()),
        ]);
    }

    /**
     * Cuadro de pagos en Excel (formato semanal por frentes) con la pestaña y los filtros del listado.
     */
    public function export(Request $request, PagoExportService $exporter): JsonResponse|StreamedResponse
    {
        if (!$this->pagos->canView($request->user())) {
            return $this->forbidden();
        }

        $scope = $request->query('scope', PagoService::SCOPE_SCHEDULED);
        $filters = $request->only(['search', 'status', 'project_id', 'frente', 'priority', 'source_type', 'scheduled_for']);
        $spreadsheet = $exporter->build($this->pagos->exportRows($request->user(), $scope, $filters), $filters['scheduled_for'] ?? null);

        $name = 'Cuentas por pagar ' . ($filters['scheduled_for'] ?? now()->toDateString()) . '.xlsx';

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function show(Request $request, PagoCuenta $cuenta): JsonResponse
    {
        if (!$this->pagos->canAccess($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return response()->json(['status' => 'success', 'data' => $this->pagos->getCuenta($cuenta, $request->user())]);
    }

    public function store(StorePagoCuentaRequest $request): JsonResponse
    {
        return $this->attempt('registrar la cuenta', function () use ($request) {
            $cuenta = $this->pagos->createManual($request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Cuenta registrada. Queda pendiente de priorizar.',
                'data' => $this->pagos->getCuenta($cuenta, $request->user()),
            ], 201);
        });
    }

    public function prioritize(PrioritizePagosRequest $request): JsonResponse
    {
        return $this->attempt('priorizar los pagos', function () use ($request) {
            $count = $this->pagos->prioritize(
                $request->validated('ids'),
                $request->validated('priority'),
                $request->validated('scheduled_for'),
                $request->validated('comment'),
                $request->user()
            );

            return response()->json(['status' => 'success', 'message' => "{$count} cuenta(s) priorizada(s) y programada(s).", 'data' => ['count' => $count]]);
        });
    }

    public function unschedule(Request $request, PagoCuenta $cuenta): JsonResponse
    {
        if (!$this->pagos->canAccess($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('desprogramar la cuenta', function () use ($request, $cuenta) {
            $cuenta = $this->pagos->unschedule($cuenta, $request->input('comment'), $request->user());

            return response()->json(['status' => 'success', 'message' => 'Cuenta desprogramada.', 'data' => $this->pagos->getCuenta($cuenta, $request->user())]);
        });
    }

    public function uploadUrl(Request $request): JsonResponse
    {
        if (!$this->pagos->canPay($request->user())) {
            return $this->forbidden('No tienes permiso para registrar pagos.');
        }
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt('preparar la carga del archivo', fn () => response()->json([
            'status' => 'success',
            'data' => $this->pagos->requestUpload($data['file_name'], (int) $data['file_size'], $data['mime_type'] ?? null, $request->user()),
        ]));
    }

    public function pay(PayPagoCuentaRequest $request, PagoCuenta $cuenta): JsonResponse
    {
        if (!$this->pagos->canAccess($cuenta, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('registrar el pago', function () use ($request, $cuenta) {
            $cuenta = $this->pagos->pay($cuenta, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $cuenta->status === PagoService::STATUS_PAGADA ? 'Pago registrado. La cuenta quedó pagada.' : 'Abono registrado.',
                'data' => $this->pagos->getCuenta($cuenta, $request->user()),
            ]);
        });
    }

    public function annul(Request $request, PagoCuenta $cuenta): JsonResponse
    {
        if (!$this->pagos->canAccess($cuenta, $request->user())) {
            return $this->forbidden();
        }
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']], ['comment.required' => 'Indica el motivo de la anulación.']);

        return $this->attempt('anular la cuenta', function () use ($request, $cuenta, $data) {
            $cuenta = $this->pagos->annul($cuenta, $data['comment'], $request->user());

            return response()->json(['status' => 'success', 'message' => 'Cuenta anulada.', 'data' => $this->pagos->getCuenta($cuenta, $request->user())]);
        });
    }

    public function downloadSupport(Request $request, PagoMovimiento $movimiento): JsonResponse
    {
        if (!$movimiento->file_path || !$this->pagos->canAccess($movimiento->cuenta, $request->user())) {
            return $this->forbidden();
        }

        return $this->attempt('descargar el soporte', fn () => response()->json([
            'status' => 'success',
            'data' => ['download_url' => $this->pagos->supportDownloadUrl($movimiento), 'name' => $movimiento->file_name],
        ]));
    }
}
