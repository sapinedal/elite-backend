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
            $category = $request->query('category');
            $search = $request->query('search');

            $contracts = $this->contractService->getFilteredContracts($category, $search);

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
     * Contract Metrics and KPIs Summary
     */
    public function kpis(): JsonResponse
    {
        try {
            $metrics = $this->contractService->getContractMetrics();

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
