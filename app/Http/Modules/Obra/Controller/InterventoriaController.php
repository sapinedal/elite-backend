<?php

namespace App\Http\Modules\Obra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Obra\Service\InterventoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos del último informe semanal de interventoría para el Módulo Técnico.
 */
class InterventoriaController extends Controller
{
    public function __construct(protected InterventoriaService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $numero = $request->integer('numero') ?: null;

        return response()->json(['success' => true, 'data' => $this->service->dashboard($numero)]);
    }
}
