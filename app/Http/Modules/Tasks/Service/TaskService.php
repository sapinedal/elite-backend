<?php

namespace App\Http\Modules\Tasks\Service;

use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Tasks\Models\TaskObservation;
use App\Http\Modules\Tasks\Models\TaskAuditLog;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskService
{
    /**
     * Obtiene y filtra el listado de tareas.
     * 
     * Ventaja técnica: Eager loading ('with') para evitar el problema de consultas N+1.
     * Al traer de un solo golpe las relaciones 'requestedBy', 'responsible' y 'area',
     * la aplicación es extremadamente veloz y escala de forma eficiente.
     */
    public function listTasks(User $user, ?int $projectId = null, array $filters = [])
    {
        // Solo tareas de los proyectos del usuario; si hay proyecto activo, únicamente ese
        $query = Task::query()->accessibleBy($user);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Aplicamos los filtros generales (Área, Responsable y Búsqueda por Texto)
        if (!empty($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }

        if (!empty($filters['responsible_id'])) {
            $query->where('responsible_id', $filters['responsible_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                  ->orWhereHas('responsible', function ($sub) use ($search) {
                      $sub->where('name', 'ilike', '%' . $search . '%')
                          ->orWhere('email', 'ilike', '%' . $search . '%');
                  })
                  ->orWhereHas('area', function ($sub) use ($search) {
                      $sub->where('name', 'ilike', '%' . $search . '%');
                  });
            });
        }

        // Si se solicita paginación, calculamos estadísticas y devolvemos la respuesta paginada
        if ((isset($filters['paginate']) && $filters['paginate'] === 'true') || isset($filters['page']) || isset($filters['per_page'])) {
            // Clonamos el query para calcular estadísticas basándonos únicamente en los filtros del sidebar
            $statsQuery = clone $query;
            $statusCounts = $statsQuery->select('status', DB::raw('count(*) as count'))
                                       ->groupBy('status')
                                       ->get()
                                       ->pluck('count', 'status');

            $statsQuery2 = clone $query;
            // Críticas = P0 que siguen abiertas (una tarea completada ya no es crítica)
            $p0Count = $statsQuery2->where('priority', 'P0')
                                   ->where('status', '!=', 'Completada')
                                   ->count();

            $statsQuery3 = clone $query;
            $totalCount = $statsQuery3->count();

            // Ahora aplicamos los filtros específicos de tarjetas (Estado y Prioridad)
            $this->applyCardFilters($query, $filters);

            $perPage = intval($filters['per_page'] ?? 15);
            $paginated = $query->with([
                                   'project:id,code,name',
                                   'requestedBy:id,name',
                                   'responsible:id,name',
                                   'area:id,name'
                               ])
                               ->withCount('observations')
                               ->orderBy('created_at', 'desc')
                               ->paginate($perPage);

            return [
                'pagination' => $paginated,
                'stats' => [
                    'total' => $totalCount,
                    'todo' => intval($statusCounts->get('Por hacer', 0)),
                    'in_progress' => intval($statusCounts->get('En progreso', 0)),
                    'completed' => intval($statusCounts->get('Completada', 0)),
                    'waiting' => intval($statusCounts->get('En espera', 0)),
                    'critical' => $p0Count,
                ]
            ];
        }

        // Si no se pide paginación, aplicamos todos los filtros y devolvemos la lista completa
        $this->applyCardFilters($query, $filters);

        return $query->with([
                         'project:id,code,name',
                         'requestedBy:id,name',
                         'responsible:id,name',
                         'area:id,name'
                     ])
                     ->withCount('observations')
                     ->orderBy('created_at', 'desc')
                     ->get();
    }

    /**
     * Aplica los filtros de tarjetas (Estado y Prioridad).
     *
     * - 'status' admite varios estados (array o lista separada por comas): "En espera,En progreso".
     * - 'priority' admite una o varias prioridades del mismo modo.
     * - Si se filtra por P0 (Críticas) sin elegir estados, se excluyen las completadas.
     */
    private function applyCardFilters($query, array $filters): void
    {
        $statuses = $this->toList($filters['status'] ?? null);
        $priorities = $this->toList($filters['priority'] ?? null);

        if (!empty($priorities)) {
            $query->whereIn('priority', $priorities);
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        } elseif (in_array('P0', $priorities, true)) {
            $query->where('status', '!=', 'Completada');
        }
    }

    /**
     * Normaliza un filtro que puede llegar como string, lista separada por comas o array.
     */
    private function toList($value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }
        $items = is_array($value) ? $value : explode(',', (string) $value);
        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    /**
     * Registra una nueva tarea e inserta su log inicial en la auditoría.
     */
    public function createTask(array $data, int $userId): Task
    {
        // Envolver en una transacción de base de datos para asegurar atomicidad.
        // Si la tarea se crea pero falla la inserción de auditoría, se revierte todo
        // evitando estados inconsistentes en la base de datos.
        return DB::transaction(function () use ($data, $userId) {
            $data['requested_by_id'] = $userId;
            
            // Si el estado inicial es Completada, asignamos la fecha real actual
            if (isset($data['status']) && $data['status'] === 'Completada') {
                $data['actual_end_date'] = $data['actual_end_date'] ?? Carbon::now()->toDateString();
            }

            $task = Task::create($data);

            // Crear el registro de auditoría de creación
            TaskAuditLog::create([
                'task_id' => $task->id,
                'user_id' => $userId,
                'action' => 'created',
                'changes' => [
                    'project_id' => ['new' => $task->project_id],
                    'title' => ['new' => $task->title],
                    'status' => ['new' => $task->status],
                    'priority' => ['new' => $task->priority]
                ]
            ]);

            $task->load([
                'project:id,code,name',
                'requestedBy:id,name',
                'responsible:id,name',
                'area:id,name'
            ]);
            $task->loadCount('observations');

            return $task;
        });
    }

    /**
     * Modifica una tarea y genera automáticamente las auditorías de cambios.
     */
    public function updateTask(Task $task, array $data, int $userId): Task
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            // Guardamos el estado original relevante
            $original = $task->only([
                'project_id', 'title', 'priority', 'status', 'responsible_id', 'area_id', 'start_date', 'scheduled_end_date', 'actual_end_date'
            ]);

            // Mapeo automático de fecha de finalización real
            if (isset($data['status'])) {
                if ($data['status'] === 'Completada') {
                    $data['actual_end_date'] = $data['actual_end_date'] ?? Carbon::now()->toDateString();
                } else {
                    $data['actual_end_date'] = null; // Si retrocede a en progreso o espera
                }
            }

            // Actualizamos el modelo localmente en memoria
            $task->fill($data);

            // Analizamos qué campos cambiaron comparando valor viejo y nuevo
            $changes = [];
            foreach ($original as $key => $oldValue) {
                if ($task->isDirty($key)) {
                    $newValue = $task->getAttribute($key);

                    // Estandarizar fechas para evitar falsos positivos
                    if ($oldValue instanceof Carbon || $oldValue instanceof \DateTime) {
                        $oldValue = $oldValue->toDateString();
                    }
                    if ($newValue instanceof Carbon || $newValue instanceof \DateTime) {
                        $newValue = $newValue->toDateString();
                    }

                    if ($oldValue != $newValue) {
                        $changes[$key] = [
                            'old' => $oldValue,
                            'new' => $newValue
                        ];
                    }
                }
            }

            // Persistimos los cambios en la base de datos
            $task->save();

            // Solo creamos log de auditoría si hubo algún cambio real en los campos vigilados
            if (!empty($changes)) {
                TaskAuditLog::create([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'action' => 'updated',
                    'changes' => $changes
                ]);
            }

            $task->load([
                'project:id,code,name',
                'requestedBy:id,name',
                'responsible:id,name',
                'area:id,name'
            ]);
            $task->loadCount('observations');

            return $task;
        });
    }

    /**
     * Elimina una tarea del sistema y registra el log final.
     */
    public function deleteTask(Task $task, int $userId): void
    {
        DB::transaction(function () use ($task, $userId) {
            // Registramos un log histórico antes de aplicar el soft delete
            TaskAuditLog::create([
                'task_id' => $task->id,
                'user_id' => $userId,
                'action' => 'deleted',
                'changes' => [
                    'status' => [
                        'old' => $task->status,
                        'new' => 'Eliminada (Soft Delete)'
                    ]
                ]
            ]);

            $task->delete();
        });
    }

    /**
     * Añade una observación rápida durante las reuniones de daily o seguimiento.
     */
    public function addObservation(Task $task, string $observation, int $userId): TaskObservation
    {
        return TaskObservation::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'observation' => $observation
        ]);
    }
}
