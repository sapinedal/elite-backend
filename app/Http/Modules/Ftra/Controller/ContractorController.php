<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ftra\Service\ContractorService;
use App\Http\Modules\Ftra\Request\StoreContractorRequest;
use App\Http\Modules\Ftra\Request\UpdateContractorRequest;
use Illuminate\Http\Request;

class ContractorController extends Controller
{
    protected $contractorService;

    public function __construct(ContractorService $contractorService)
    {
        $this->contractorService = $contractorService;
    }

    /**
     * Helper to verify if the user has editor permissions.
     */
    protected function checkEditorPermission()
    {
        return true; // Forzado a true temporalmente para permitir pruebas de CRUD sin Spatie
    }

    /**
     * Devuelve el listado de contratistas con filtros.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'is_active', 'per_page']);
        $contractors = $this->contractorService->listContractors($filters);
        
        return response()->json($contractors);
    }

    /**
     * Muestra los detalles de un contratista específico.
     */
    public function show(FtraContractor $contractor)
    {
        return response()->json($contractor);
    }

    /**
     * Registra un nuevo contratista.
     */
    public function store(StoreContractorRequest $request)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para agregar contratistas de FTRA.'
            ], 403);
        }

        $contractor = $this->contractorService->createContractor($request->validated());

        return response()->json([
            'message' => 'Contratista guardado exitosamente.',
            'contractor' => $contractor
        ], 201);
    }

    /**
     * Actualiza un contratista existente.
     */
    public function update(UpdateContractorRequest $request, FtraContractor $contractor)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para modificar contratistas de FTRA.'
            ], 403);
        }

        $updatedContractor = $this->contractorService->updateContractor($contractor, $request->validated());

        return response()->json([
            'message' => 'Contratista actualizado exitosamente.',
            'contractor' => $updatedFormat ?? $updatedContractor
        ]);
    }

    /**
     * Elimina un contratista.
     */
    public function destroy(FtraContractor $contractor)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar contratistas de FTRA.'
            ], 403);
        }

        $this->contractorService->deleteContractor($contractor);

        return response()->json([
            'message' => 'Contratista eliminado exitosamente.'
        ]);
    }
}
