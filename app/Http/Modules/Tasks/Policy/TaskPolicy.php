<?php

namespace App\Http\Modules\Tasks\Policy;

use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Users\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Una tarea solo es visible y editable para quien tiene acceso a su proyecto.
 */
class TaskPolicy
{
    public function view(User $user, Task $task): Response
    {
        return $user->canAccessProject($task->project_id)
            ? Response::allow()
            : Response::deny('No tienes acceso al proyecto de esta tarea.');
    }

    public function update(User $user, Task $task): Response
    {
        return $this->view($user, $task);
    }

    public function addObservation(User $user, Task $task): Response
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): Response
    {
        if (!$user->can('bitacora.eliminar')) {
            return Response::deny('No tienes permisos para eliminar tareas de la bitácora.');
        }

        return $this->view($user, $task);
    }
}
