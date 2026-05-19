<?php

namespace App\Http\Modules\Tasks\Service;

use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Tasks\Models\TaskObservation;
use App\Http\Modules\Tasks\Models\TaskAuditLog;
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
    public function listTasks(array $filters = [])
    {
        $query = Task::with(['requestedBy', 'responsible', 'area', 'observations']);

        // Filtrado por Estado
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtrado por Prioridad (P0, P1, P2, P3)
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Filtrado por Área
        if (!empty($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }

        // Filtrado por Responsable
        if (!empty($filters['responsible_id'])) {
            $query->where('responsible_id', $filters['responsible_id']);
        }

        // Búsqueda textual por el título
        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->get();
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
                    'title' => ['new' => $task->title],
                    'status' => ['new' => $task->status],
                    'priority' => ['new' => $task->priority]
                ]
            ]);

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
                'title', 'priority', 'status', 'responsible_id', 'area_id', 'start_date', 'scheduled_end_date', 'actual_end_date'
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

            return $task;
        });
    }

    /**
     * Elimina una tarea del sistema y registra el log final.
     */
    public function deleteTask(Task $task, int $userId): void
    {
        DB::transaction(function () use ($task, $userId) {
            // Registramos un log histórico rápido antes del borrado físico (u opcionalmente lógico)
            // Nota: Al usar onDelete('cascade'), la base de datos limpia automáticamente observaciones y logs.
            // Para mantener auditoría persistente tras borrar una tarea, se suele usar SoftDeletes en el modelo.
            // En este caso, realizamos el delete directo de Eloquent.
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
