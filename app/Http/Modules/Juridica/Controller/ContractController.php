<?php

namespace App\Http\Modules\Juridica\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Juridica\Service\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ContractController extends Controller
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * List all contracts with relationships and filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projectId = $request->query('project_id') ? (int)$request->query('project_id') : null;
            $towerId = $request->query('tower_id') ? (int)$request->query('tower_id') : null;
            $category = $request->query('category');
            $search = $request->query('search');

            $contracts = $this->contractService->getFilteredContracts($projectId, $towerId, $category, $search);

            return response()->json([
                'status' => 'success',
                'data' => $contracts,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar contratos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new contract
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nro' => 'required|string|max:100',
                'project_id' => 'nullable|integer|exists:projects,id',
                'tower_id' => 'nullable|integer|exists:towers,id',
                'contractor_id' => 'nullable|integer',
                'contractor_name_raw' => 'required|string|max:255',
                'type' => 'required|string|max:100',
                'category' => 'nullable|string|max:100',
                'object' => 'nullable|string',
                'amount' => 'nullable|numeric',
                'status' => 'nullable|string|max:50',
                'drive_link' => 'nullable|string|max:500',
                'policy' => 'nullable|array',
                'policy.policy_number' => 'nullable|string|max:100',
                'policy.insurance_company' => 'nullable|string|max:100',
                'policy.insured_value' => 'nullable|numeric',
                'policy.end_date' => 'nullable|string',
            ]);

            $contract = $this->contractService->createContract($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato creado exitosamente',
                'data' => $contract,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update specified contract
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nro' => 'nullable|string|max:100',
                'project_id' => 'nullable|integer',
                'tower_id' => 'nullable|integer',
                'contractor_id' => 'nullable|integer',
                'contractor_name_raw' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:100',
                'category' => 'nullable|string|max:100',
                'object' => 'nullable|string',
                'amount' => 'nullable|numeric',
                'status' => 'nullable|string|max:50',
                'drive_link' => 'nullable|string|max:500',
                'policy' => 'nullable|array',
            ]);

            $contract = $this->contractService->updateContract($id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato actualizado exitosamente',
                'data' => $contract,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove specified contract
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->contractService->deleteContract($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Contrato eliminado exitosamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Contract Metrics and KPIs Summary
     */
    public function kpis(Request $request): JsonResponse
    {
        try {
            $projectId = $request->query('project_id') ? (int)$request->query('project_id') : null;
            $metrics = $this->contractService->getContractMetrics($projectId);

            return response()->json([
                'status' => 'success',
                'data' => $metrics,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar indicadores de contratos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
