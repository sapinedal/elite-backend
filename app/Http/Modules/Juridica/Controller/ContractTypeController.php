<?php

namespace App\Http\Modules\Juridica\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Juridica\Service\ContractTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ContractTypeController extends Controller
{
    protected ContractTypeService $contractTypeService;

    public function __construct(ContractTypeService $contractTypeService)
    {
        $this->contractTypeService = $contractTypeService;
    }

    public function index(): JsonResponse
    {
        try {
            $types = $this->contractTypeService->getAllContractTypes();
            return response()->json([
                'status' => 'success',
                'data' => $types,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar tipos de contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            $type = $this->contractTypeService->createContractType($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de contrato creado exitosamente',
                'data' => $type,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear tipo de contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            $type = $this->contractTypeService->updateContractType($id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de contrato actualizado exitosamente',
                'data' => $type,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar tipo de contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->contractTypeService->deleteContractType($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de contrato eliminado exitosamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar tipo de contrato: ' . $e->getMessage(),
            ], 500);
        }
    }
}
