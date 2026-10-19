<?php

namespace App\Http\Modules\Tasks\Request;

use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Users\Models\User;
use Illuminate\Validation\Validator;

/**
 * Regla de negocio de la bitácora: el responsable de una tarea debe ser una persona activa
 * que pertenezca al área asignada a la tarea.
 */
trait ValidatesResponsibleArea
{
    /**
     * @param Task|null $task Tarea existente cuando se edita; null al crear.
     */
    protected function validateResponsibleArea(Validator $validator, ?Task $task = null): void
    {
        if ($validator->errors()->hasAny(['responsible_id', 'area_id'])) {
            return;
        }

        $responsibleChanged = $this->has('responsible_id') && (int) $this->input('responsible_id') !== (int) $task?->responsible_id;
        $areaChanged = $this->has('area_id') && (int) $this->input('area_id') !== (int) $task?->area_id;

        // Al editar sin tocar responsable ni área (ej. solo cambiar el estado) no se revalida,
        // para no bloquear tareas antiguas cuyo responsable ya no esté activo o haya cambiado de área
        if (!$responsibleChanged && !$areaChanged) {
            return;
        }

        $responsibleId = $this->has('responsible_id') ? $this->input('responsible_id') : $task?->responsible_id;
        $areaId = $this->has('area_id') ? $this->input('area_id') : $task?->area_id;

        if (!$responsibleId) {
            return;
        }

        // El modelo User usa SoftDeletes: find() no devuelve usuarios eliminados (inactivos)
        $responsible = User::find($responsibleId);
        if (!$responsible) {
            $validator->errors()->add('responsible_id', 'El responsable seleccionado no está activo.');
            return;
        }

        if ($areaId && (int) $responsible->area_id !== (int) $areaId) {
            $validator->errors()->add(
                'responsible_id',
                "{$responsible->name} no pertenece al área seleccionada. Elige una persona de esa área."
            );
        }
    }
}
