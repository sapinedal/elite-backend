<?php

namespace App\Http\Modules\CuentasCobro\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos para radicar una cuenta de cobro. También se usa al reenviar una devuelta (ResubmitCuentaCobroRequest),
 * donde la cuenta de cobro es opcional porque se puede conservar la anterior.
 * Los adjuntos obligatorios según el tipo los valida CuentaCobroService.
 */
class StoreCuentaCobroRequest extends FormRequest
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
            'tipo_id' => ['required', 'integer', 'exists:cuentas_cobro_tipos,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'tower_id' => ['nullable', 'integer', Rule::exists('towers', 'id')->where('project_id', $projectId)],
            'contract_id' => ['nullable', 'integer', Rule::exists('contratacion_contracts', 'id')->where('project_id', $projectId)->whereNull('deleted_at')],
            'contractor_id' => ['required', 'integer', Rule::exists('ftra_contractors', 'id')->where('is_active', true)],
            'document_number' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'upload_token' => [$this->fileRequired ? 'required' : 'nullable', 'uuid'],
            'attachment_tokens' => ['nullable', 'array', 'max:20'],
            'attachment_tokens.*' => ['required', 'uuid', 'distinct'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_id' => 'tipo de cuenta de cobro',
            'project_id' => 'proyecto',
            'tower_id' => 'frente',
            'contract_id' => 'contrato',
            'contractor_id' => 'proveedor',
            'document_number' => 'número de la cuenta de cobro',
            'amount' => 'valor',
            'description' => 'concepto',
            'upload_token' => 'cuenta de cobro',
            'attachment_tokens' => 'adjuntos',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_token.required' => 'Adjunta la cuenta de cobro.',
            'tower_id.exists' => 'El frente no pertenece al proyecto seleccionado.',
            'contract_id.exists' => 'El contrato no pertenece al proyecto seleccionado.',
        ];
    }
}
