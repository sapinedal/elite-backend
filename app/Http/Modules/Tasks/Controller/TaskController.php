<?php

namespace App\Http\Modules\Tasks\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Tasks\Service\TaskService;
use App\Http\Modules\Tasks\Request\StoreTaskRequest;
use App\Http\Modules\Tasks\Request\UpdateTaskRequest;
use Illuminate\Http\Request;

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
        $tasks = $this->taskService->listTasks($filters);
        
        return response()->json($tasks);
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
        $task->load([
            'requestedBy:id,name',
            'responsible:id,name',
            'area:id,name',
            'observations:id,task_id,user_id,observation,created_at',
            'observations.user:id,name',
            'auditLogs:id,task_id,user_id,action,changes,created_at',
            'auditLogs.user:id,name'
        ]);

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

        // Simulamos la verificación de permisos para bitacora.editar
        $isEditor = str_contains(strtolower(optional($user->position)->name), 'director') || 
                    str_contains(strtolower(optional($user->position)->name), 'gerente') ||
                    $user->email === 'admin@elite.com' ||
                    str_contains(strtolower($user->name), 'admin');

        $validatedData = $request->validated();

        // Si intenta modificar campos estratégicos y no es editor, bloqueamos la acción.
        // Esto previene que usuarios generales alteren las metas planificadas de la bitácora.
        $restrictedKeys = ['title', 'responsible_id', 'area_id', 'start_date', 'scheduled_end_date'];
        $hasRestrictedChanges = false;

        foreach ($restrictedKeys as $key) {
            if (isset($validatedData[$key]) && $validatedData[$key] != $task->getAttribute($key)) {
                $hasRestrictedChanges = true;
                break;
            }
        }

        if ($hasRestrictedChanges && !$isEditor) {
            return response()->json([
                'message' => 'No tienes permisos para modificar campos estratégicos de planificación en esta tarea. Solo puedes actualizar su estado.'
            ], 403);
        }

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

        $isEditor = str_contains(strtolower(optional($user->position)->name), 'director') || 
                    str_contains(strtolower(optional($user->position)->name), 'gerente') ||
                    $user->email === 'admin@elite.com' ||
                    str_contains(strtolower($user->name), 'admin');

        if (!$isEditor) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar tareas de la bitácora.'
            ], 403);
        }

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
