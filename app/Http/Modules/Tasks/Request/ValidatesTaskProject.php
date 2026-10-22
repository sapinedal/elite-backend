<?php

namespace App\Http\Modules\Tasks\Request;

use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Users\Models\User;
use Illuminate\Validation\Validator;

/**
 * Regla de negocio de la bitácora por proyectos: la tarea solo puede quedar en un proyecto
 * al que tenga acceso quien la registra, y el responsable debe pertenecer a ese proyecto.
 */
trait ValidatesTaskProject
{
    /**
     * Si el body no trae project_id, se toma el proyecto activo (header X-Project-Id).
     */
    protected function mergeActiveProject(): void
    {
        $activeProjectId = $this->attributes->get(ResolveActiveProject::ATTRIBUTE);

        if (!$this->has('project_id') && $activeProjectId) {
            $this->merge(['project_id' => $activeProjectId]);
        }
    }

    /**
     * @param Task|null $task Tarea existente cuando se edita; null al crear.
     */
    protected function validateTaskProject(Validator $validator, ?Task $task = null): void
    {
        if ($validator->errors()->hasAny(['project_id', 'responsible_id'])) {
            return;
        }

        $projectChanged = $this->has('project_id') && (int) $this->input('project_id') !== (int) $task?->project_id;
        $responsibleChanged = $this->has('responsible_id') && (int) $this->input('responsible_id') !== (int) $task?->responsible_id;

        // Al editar sin tocar proyecto ni responsable no se revalida
        if (!$projectChanged && !$responsibleChanged) {
            return;
        }

        $projectId = (int) ($this->has('project_id') ? $this->input('project_id') : $task?->project_id);

        if ($projectChanged && !$this->user()?->canAccessProject($projectId)) {
            $validator->errors()->add('project_id', 'No tienes acceso al proyecto seleccionado.');
            return;
        }

        $responsibleId = $this->has('responsible_id') ? $this->input('responsible_id') : $task?->responsible_id;
        if (!$responsibleId) {
            return;
        }

        // Si el responsable no existe o está inactivo, ValidatesResponsibleArea ya reporta el error
        $responsible = User::find($responsibleId);
        if ($responsible && !$responsible->canAccessProject($projectId)) {
            $validator->errors()->add(
                'responsible_id',
                "{$responsible->name} no está asignado a este proyecto. Elige una persona del proyecto."
            );
        }
    }
}
