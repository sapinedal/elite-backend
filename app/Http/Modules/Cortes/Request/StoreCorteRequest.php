<?php

namespace App\Http\Modules\Cortes\Request;

use App\Http\Modules\Cortes\Models\Corte;
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

    /**
     * "3" o "corte 3" llegan como "CORTE 3" para validar que no se repita en el contrato.
     * Un corte anterior a la numeración que se reenvía con su mismo código lo conserva.
     */
    protected function prepareForValidation(): void
    {
        $current = $this->route('corte')?->code;
        $number = Corte::numberOf($this->input('code'));

        if ($this->input('code') !== $current && $number !== null) {
            $this->merge(['code' => Corte::codeFor($number)]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->input('project_id');

        return [
            // CORTE 1, CORTE 2... por contrato. No se repite dentro del mismo contrato (ignora el corte que se reenvía)
            'code' => [
                'required', 'string', 'max:100',
                Rule::unique('cortes', 'code')
                    ->where('contract_id', $this->input('contract_id'))
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
            'code.unique' => 'Ese número de corte ya está radicado en el contrato. Elige el siguiente.',
            'tower_id.exists' => 'La torre no pertenece al proyecto seleccionado.',
            'contract_id.exists' => 'El contrato no pertenece al proyecto seleccionado.',
            'upload_token.required' => 'Adjunta el archivo del corte.',
        ];
    }
}
