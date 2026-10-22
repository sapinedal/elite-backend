<?php

namespace App\Http\Modules\Tasks\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Tasks\Service\TaskService;
use App\Http\Modules\Tasks\Request\StoreTaskRequest;
use App\Http\Modules\Tasks\Request\UpdateTaskRequest;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Devuelve el listado de tareas con los filtros aplicados.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'area_id', 'responsible_id', 'search', 'page', 'per_page', 'paginate']);
        $projectId = $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
        $tasks = $this->taskService->listTasks($request->user(), $projectId, $filters);
        
        return response()->json($tasks);
    }

    /**
     * IDs de los usuarios que pueden ser responsables de tareas en el proyecto activo:
     * los asignados al proyecto y quienes ven todos los proyectos.
     */
    public function assignableUsers(Request $request)
    {
        $projectId = $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
        if (!$projectId) {
            return response()->json(['message' => 'Selecciona un proyecto.'], 422);
        }

        $memberIds = Project::findOrFail($projectId)->users()->pluck('users.id');
        $globalIds = User::permission('proyectos.ver_todos')->pluck('id');

        return response()->json([
            'user_ids' => $memberIds->merge($globalIds)->unique()->values(),
        ]);
    }

    /**
     * Registra una nueva tarea en la bitácora.
     * Accesible por todo el personal de la empresa.
     */
    public function store(StoreTaskRequest $request)
    {
        $user = auth()->user() ?: \App\Http\Modules\Users\Models\User::first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $task = $this->taskService->createTask($request->validated(), $user->id);

        return response()->json([
            'message' => 'Tarea agregada correctamente a la bitácora.',
            'task' => $task
        ], 201);
    }

    /**
     * Muestra una sola tarea con todo su historial y comentarios.
     */
    public function show(Task $task)
    {
        Gate::authorize('view', $task);

        $task->load([
            'project:id,code,name',
            'requestedBy:id,name',
            'responsible:id,name',
            'area:id,name',
            'observations:id,task_id,user_id,observation,created_at',
            'observations.user:id,name',
            'auditLogs:id,task_id,user_id,action,changes,created_at',
            'auditLogs.user:id,name'
        ]);
        $task->loadCount('observations');

        return response()->json($task);
    }

    /**
     * Actualiza los datos o el estado de una tarea.
     * 
     * Regla de Negocio: Todo el personal puede actualizar únicamente el estado (status)
     * de las tareas para indicar avance, pero campos core como título, responsable,
     * área o fechas de planificación quedan reservados para roles de administración / edición.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $user = auth()->user() ?: \App\Http\Modules\Users\Models\User::first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        Gate::authorize('update', $task);

        $validatedData = $request->validated();
        $updatedTask = $this->taskService->updateTask($task, $validatedData, $user->id);

        return response()->json([
            'message' => 'Tarea actualizada exitosamente.',
            'task' => $updatedTask
        ]);
    }

    /**
     * Elimina una tarea de la bitácora.
     * Reservado estrictamente para roles de edición/administración.
     */
    public function destroy(Task $task)
    {
        $user = auth()->user() ?: \App\Http\Modules\Users\Models\User::first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        // Requiere bitacora.eliminar y acceso al proyecto de la tarea
        Gate::authorize('delete', $task);

        $this->taskService->deleteTask($task, $user->id);

        return response()->json([
            'message' => 'Tarea eliminada de la bitácora exitosamente.'
        ]);
    }

    /**
     * Añade una observación o post-it rápido (Daily Standup) a la tarea.
     */
    public function storeObservation(Request $request, Task $task)
    {
        Gate::authorize('addObservation', $task);

        $request->validate([
            'observation' => 'required|string|max:1000'
        ]);

        $user = auth()->user() ?: \App\Http\Modules\Users\Models\User::first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $observation = $this->taskService->addObservation($task, $request->observation, $user->id);

        // Retornamos cargado el usuario para que el frontend lo dibuje en caliente inmediatamente
        $observation->load('user');

        return response()->json([
            'message' => 'Observación registrada exitosamente.',
            'observation' => $observation
        ], 201);
    }
}
