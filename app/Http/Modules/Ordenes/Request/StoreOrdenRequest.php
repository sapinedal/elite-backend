<?php

namespace App\Http\Modules\Ordenes\Request;

use App\Http\Modules\Configuracion\Models\Tower;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:2000'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'tower_id' => ['nullable', 'integer', 'exists:towers,id'],
            'chapter' => ['required', 'string', 'max:255'],
            'activity' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $projectId = (int) $this->input('project_id');
                $towerId = $this->input('tower_id');

                if ($projectId && !$this->user()->canAccessProject($projectId)) {
                    $validator->errors()->add('project_id', 'No tienes acceso a este proyecto.');
                }
                if ($towerId && Tower::whereKey($towerId)->value('project_id') !== $projectId) {
                    $validator->errors()->add('tower_id', 'La torre no pertenece al proyecto seleccionado.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'descripción',
            'project_id' => 'presupuesto',
            'tower_id' => 'torre',
            'chapter' => 'capítulo',
            'activity' => 'actividad',
            'comment' => 'observaciones',
        ];
    }
}
