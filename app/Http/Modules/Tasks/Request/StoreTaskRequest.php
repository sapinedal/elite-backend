<?php

namespace App\Http\Modules\Tasks\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * 
     * Siguiendo las reglas de negocio, todo el personal está autorizado para registrar tareas.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Reglas de validación aplicadas al cuerpo del request.
     * 
     * Ventaja técnica: Centraliza la lógica de filtrado de datos, impidiendo que lleguen
     * valores corruptos, nulos o mal formateados al controlador o al servicio.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255', // Descripción explícita de la tarea
            
            // Prioridades estrictas según el feedback del desarrollador: P0 a P3
            'priority' => 'required|string|in:P0,P1,P2,P3', 
            
            // Estado inicial opcional, si no viene por defecto será "Por hacer"
            'status' => 'sometimes|string|in:Por hacer,En espera,En progreso,Completada', 
            
            // Llaves foráneas: Verificamos de forma ágil que el responsable y el área existan
            'responsible_id' => 'nullable|exists:users,id',
            'area_id' => 'nullable|exists:areas,id',
            
            // Fechas
            'start_date' => 'nullable|date',
            'scheduled_end_date' => 'nullable|date|after_or_equal:start_date', // Garantiza orden cronológico coherente
        ];
    }
}
