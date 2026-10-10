<?php

namespace App\Http\Modules\Configuracion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Service\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * Get list of projects
     */
    public function index(): JsonResponse
    {
        try {
            $projects = $this->projectService->getAllProjects();
            return response()->json([
                'status' => 'success',
                'data' => $projects,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener proyectos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new project
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|unique:projects,code',
                'name' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'total_budget' => 'nullable|numeric',
                'is_active' => 'nullable|boolean',
            ]);

            $project = $this->projectService->createProject($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Proyecto creado exitosamente',
                'data' => $project,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified project
     */
    public function show(int $id): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectById($id);

            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Proyecto no encontrado',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $project,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified project
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'nullable|string|unique:projects,code,' . $id,
                'name' => 'nullable|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'total_budget' => 'nullable|numeric',
                'is_active' => 'nullable|boolean',
            ]);

            $project = $this->projectService->updateProject($id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Proyecto actualizado exitosamente',
                'data' => $project,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->projectService->deleteProject($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Proyecto eliminado exitosamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }
}
