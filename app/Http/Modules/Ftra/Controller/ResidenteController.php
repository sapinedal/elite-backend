<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Ftra\Models\Residente;
use App\Http\Modules\Ftra\Service\ResidenteService;
use App\Http\Modules\Ftra\Request\StoreResidenteRequest;
use App\Http\Modules\Ftra\Request\UpdateResidenteRequest;
use Illuminate\Http\Request;

class ResidenteController extends Controller
{
    protected $residenteService;

    public function __construct(ResidenteService $residenteService)
    {
        $this->residenteService = $residenteService;
    }

    /**
     * Devuelve el listado de residentes con filtros.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'is_active', 'per_page', 'all']);
        $residentes = $this->residenteService->listResidentes($filters);
        
        return response()->json($residentes);
    }

    /**
     * Muestra los detalles de un residente específico.
     */
    public function show(Residente $residente)
    {
        return response()->json($residente);
    }

    /**
     * Registra un nuevo residente.
     */
    public function store(StoreResidenteRequest $request)
    {
        $residente = $this->residenteService->createResidente($request->validated());

        return response()->json([
            'message' => 'Residente guardado exitosamente.',
            'residente' => $residente
        ], 201);
    }

    /**
     * Actualiza un residente existente.
     */
    public function update(UpdateResidenteRequest $request, Residente $residente)
    {
        $updatedResidente = $this->residenteService->updateResidente($residente, $request->validated());

        return response()->json([
            'message' => 'Residente actualizado exitosamente.',
            'residente' => $updatedResidente
        ]);
    }

    /**
     * Elimina un residente.
     */
    public function destroy(Residente $residente)
    {
        $this->residenteService->deleteResidente($residente);

        return response()->json([
            'message' => 'Residente eliminado exitosamente.'
        ]);
    }
}
