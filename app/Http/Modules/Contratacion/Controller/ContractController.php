<?php

namespace App\Http\Modules\Contratacion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Configuracion\Service\TowerService;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use App\Http\Modules\Contratacion\Request\StoreContractRequest;
use App\Http\Modules\Contratacion\Request\UpdateContractRequest;
use App\Http\Modules\Contratacion\Service\ContractDocumentCatalog;
use App\Http\Modules\Contratacion\Service\ContractService;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use App\Http\Modules\Juridica\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService,
        protected TowerService $towerService,
    ) {
    }

    protected function activeProjectId(Request $request): ?int
    {
        return $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso al proyecto de este contrato.'], 403);
    }

    protected function serverError(string $action, Throwable $e): JsonResponse
    {
        report($e);

        return response()->json(['status' => 'error', 'message' => "Error al {$action}: " . $e->getMessage()], 500);
    }

    /**
     * Catálogos del formulario: frentes (torres) del proyecto activo, tipos, estados y carpetas.
     */
    public function catalogs(Request $request): JsonResponse
    {
        $projectId = $this->activeProjectId($request);

        return response()->json([
            'status' => 'success',
            'data' => [
                'towers' => $projectId ? $this->towerService->getTowersByProject($projectId) : [],
                'contract_types' => ContractType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'statuses' => ContratacionContract::STATUSES,
                'policy_types' => ContratacionPolicy::POLICY_TYPES,
                'policy_alert_days' => config('contratacion.policy_alert_days'),
                // Tamaño máximo por archivo (configuración documental limitada por PHP), para validar antes de subir
                'max_upload_mb' => app(DocumentalSettingsService::class)->payload()['effective_max_upload_mb'],
                ...ContractDocumentCatalog::toArray(),
            ],
        ]);
    }

    /**
     * Crea un frente de obra (torre) en el proyecto activo.
     */
    public function storeTower(Request $request): JsonResponse
    {
        $projectId = $this->activeProjectId($request);
        if (!$projectId) {
            return response()->json(['status' => 'error', 'message' => 'Selecciona un proyecto activo para crear el frente de obra.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        if (Tower::where('project_id', $projectId)->whereRaw('lower(name) = ?', [mb_strtolower($validated['name'])])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Ya existe un frente de obra con ese nombre en el proyecto.'], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Frente de obra creado exitosamente.',
            'data' => $this->towerService->createTower($projectId, $validated),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'tower_id', 'contract_type_id', 'status', 'alerts_only']);

        return response()->json([
            'status' => 'success',
            'data' => $this->contractService->listContracts($request->user(), $this->activeProjectId($request), $filters),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->contractService->alerts($request->user(), $this->activeProjectId($request)),
        ]);
    }

    public function show(Request $request, ContratacionContract $contract): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->contractService->getContract($contract),
        ]);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            $contract = $this->contractService->createContract(
                [...$request->validated(), 'project_id' => $this->activeProjectId($request)],
                $request->user()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato creado y carpetas generadas en el repositorio documental.',
                'data' => $this->contractService->getContract($contract),
            ], 201);
        } catch (Throwable $e) {
            return $this->serverError('crear el contrato', $e);
        }
    }

    public function update(UpdateContractRequest $request, ContratacionContract $contract): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }

        try {
            $contract = $this->contractService->updateContract($contract, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato actualizado exitosamente.',
                'data' => $this->contractService->getContract($contract),
            ]);
        } catch (Throwable $e) {
            return $this->serverError('actualizar el contrato', $e);
        }
    }

    public function destroy(Request $request, ContratacionContract $contract): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }

        $this->contractService->deleteContract($contract);

        return response()->json([
            'status' => 'success',
            'message' => 'Contrato eliminado. Sus archivos se conservan en el repositorio documental.',
        ]);
    }
}
