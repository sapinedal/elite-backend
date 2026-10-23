<?php

namespace App\Http\Modules\Cortes\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Datos para radicar un corte. También se usa al reenviar uno devuelto (ResubmitCorteRequest),
 * donde el archivo es opcional porque se puede conservar el anterior.
 */
class StoreCorteRequest extends FormRequest
{
    protected bool $fileRequired = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->input('project_id');

        return [
            'code' => [
                'required', 'string', 'max:100',
                // El código no se repite dentro del mismo proyecto (ignora el corte que se está reenviando)
                Rule::unique('cortes', 'code')
                    ->where('project_id', $projectId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('corte')?->id),
            ],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'tower_id' => [
                'nullable', 'integer',
                Rule::exists('towers', 'id')->where('project_id', $projectId),
            ],
            'contractor_id' => ['required', 'integer', Rule::exists('ftra_contractors', 'id')->where('is_active', true)],
            'contract_id' => [
                'required', 'integer',
                Rule::exists('contratacion_contracts', 'id')->where('project_id', $projectId)->whereNull('deleted_at'),
            ],
            'upload_token' => [$this->fileRequired ? 'required' : 'nullable', 'uuid'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $projectId = (int) $this->input('project_id');
                if ($projectId && !$this->user()->canAccessProject($projectId)) {
                    $validator->errors()->add('project_id', 'No tienes acceso al proyecto seleccionado.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código del corte',
            'project_id' => 'proyecto',
            'tower_id' => 'torre',
            'contractor_id' => 'proveedor',
            'contract_id' => 'contrato relacionado',
            'upload_token' => 'archivo del corte',
            'comment' => 'comentario',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ya existe un corte con ese código en el proyecto.',
            'tower_id.exists' => 'La torre no pertenece al proyecto seleccionado.',
            'contract_id.exists' => 'El contrato no pertenece al proyecto seleccionado.',
            'upload_token.required' => 'Adjunta el archivo del corte.',
        ];
    }
}
