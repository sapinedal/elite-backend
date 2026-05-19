<?php

namespace App\Http\Modules\Tasks\Request;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
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
            'title' => 'sometimes|required|string|max:255',
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
}
