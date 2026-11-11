<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ftra\Service\ContractorService;
use Illuminate\Http\Request;

/**
 * Consulta de proveedores para FTRA. Se crean y editan desde Contratación, al registrar el contrato.
 */
class ContractorController extends Controller
{
    protected $contractorService;

    public function __construct(ContractorService $contractorService)
    {
        $this->contractorService = $contractorService;
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
}
