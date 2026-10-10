<?php

namespace App\Http\Modules\Configuracion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Service\TowerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TowerController extends Controller
{
    protected TowerService $towerService;

    public function __construct(TowerService $towerService)
    {
        $this->towerService = $towerService;
    }

    public function index(int $projectId): JsonResponse
    {
        try {
            $towers = $this->towerService->getTowersByProject($projectId);
            return response()->json([
                'status' => 'success',
                'data' => $towers,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener torres del proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
            ]);

            $tower = $this->towerService->createTower($projectId, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Torre/Etapa creada exitosamente',
                'data' => $tower,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear torre: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->towerService->deleteTower($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Torre/Etapa eliminada exitosamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar torre: ' . $e->getMessage(),
            ], 500);
        }
    }
}
