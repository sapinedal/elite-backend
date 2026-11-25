<?php

namespace App\Http\Modules\Configuracion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Service\ProveedorService;
use App\Http\Modules\Ftra\Models\FtraContractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administración de proveedores desde Configuración. Se crean desde Contratación, al registrar el contrato.
 */
class ProveedorController extends Controller
{
    public function __construct(private ProveedorService $proveedorService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->proveedorService->list($request->only(['search'])),
        ]);
    }

    public function update(Request $request, FtraContractor $proveedor): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $proveedor = $this->proveedorService->update($proveedor, $data);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Proveedor actualizado exitosamente.',
            'data' => $proveedor,
        ]);
    }

    public function destroy(FtraContractor $proveedor): JsonResponse
    {
        try {
            $this->proveedorService->delete($proveedor);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Proveedor eliminado exitosamente.',
        ]);
    }
}
