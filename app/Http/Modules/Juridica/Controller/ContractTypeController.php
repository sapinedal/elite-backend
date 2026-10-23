<?php

namespace App\Http\Modules\Juridica\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Juridica\Models\ContractType;
use App\Http\Modules\Juridica\Service\ContractTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Catálogo de tipos de contrato. Lo usan Jurídica y Contratación (rutas en ambos módulos).
 */
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
        // La validación va fuera del try: así los errores llegan como 422 y no como 500
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $this->ensureUniqueName($validated['name']);

        try {
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
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        if (!empty($validated['name'])) {
            $this->ensureUniqueName($validated['name'], $id);
        }

        try {
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
            $deleted = $this->contractTypeService->deleteContractType($id);
            return response()->json([
                'status' => 'success',
                'message' => $deleted
                    ? 'Tipo de contrato eliminado exitosamente'
                    : 'El tipo tiene contratos asociados: se desactivó para no dejarlos sin tipo.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar tipo de contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Evita tipos repetidos entre los activos (sin distinguir mayúsculas).
     */
    protected function ensureUniqueName(string $name, ?int $ignoreId = null): void
    {
        $exists = ContractType::where('is_active', true)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Ya existe un tipo de contrato con ese nombre.']);
        }
    }
}
