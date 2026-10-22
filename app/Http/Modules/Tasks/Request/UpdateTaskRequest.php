<?php

namespace App\Http\Modules\Tasks\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    use ValidatesResponsibleArea, ValidatesTaskProject;

    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * 
     * Dejamos en true a nivel de request para permitir validación en controladores 
     * y servicios mediante los permisos inyectados dinámicamente.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Reglas de validación aplicadas para la actualización de la tarea.
     * 
     * Empleamos la regla 'sometimes' para indicar que no es obligatorio mandar todos los campos
     * en cada petición de edición (ideal para actualizaciones parciales como cambiar solo el estado).
     */
    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|required|integer|exists:projects,id', // Mover la tarea a otro proyecto
            'title' => 'sometimes|required|string',
            'priority' => 'sometimes|required|string|in:P0,P1,P2,P3',
            'status' => 'sometimes|required|string|in:Por hacer,En espera,En progreso,Completada',
            'responsible_id' => 'nullable|exists:users,id',
            'area_id' => 'nullable|exists:areas,id',
            
            // Fechas
            'start_date' => 'nullable|date',
            'scheduled_end_date' => 'nullable|date|after_or_equal:start_date',
            'actual_end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }

    /**
     * El responsable debe estar activo y pertenecer al área de la tarea.
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateResponsibleArea($validator, $this->route('task')),
            fn (Validator $validator) => $this->validateTaskProject($validator, $this->route('task')),
        ];
    }
}
