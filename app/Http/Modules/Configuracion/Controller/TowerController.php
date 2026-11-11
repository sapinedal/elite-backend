<?php

namespace App\Http\Modules\Configuracion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Configuracion\Service\TowerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
                ...TowerService::LAYOUT_RULES,
            ]);

            if ($this->nameTaken($projectId, $validated['name'])) {
                return response()->json(['status' => 'error', 'message' => 'Ya existe una torre con ese nombre en el proyecto.'], 422);
            }

            $tower = $this->towerService->createTower($projectId, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Torre/Etapa creada exitosamente',
                'data' => $tower,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear torre: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Edita la torre: nombre, código y distribución de pisos y apartamentos.
     */
    public function update(Request $request, Tower $tower): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            ...TowerService::LAYOUT_RULES,
        ]);

        if (isset($validated['name']) && $this->nameTaken($tower->project_id, $validated['name'], $tower->id)) {
            return response()->json(['status' => 'error', 'message' => 'Ya existe una torre con ese nombre en el proyecto.'], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Torre actualizada exitosamente',
            'data' => $this->towerService->updateTower($tower, $validated),
        ]);
    }

    protected function nameTaken(int $projectId, string $name, ?int $exceptId = null): bool
    {
        return Tower::where('project_id', $projectId)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
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
