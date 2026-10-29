<?php

namespace App\Http\Modules\Anticipos\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos para radicar un anticipo. También se usa al reenviar uno devuelto (ResubmitAnticipoRequest),
 * donde la cuenta de cobro es opcional porque se puede conservar la anterior.
 */
class StoreAnticipoRequest extends FormRequest
{
    protected bool $fileRequired = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // El acceso al contrato (proyecto del usuario) lo valida AnticipoService
            'contract_id' => ['required', 'integer', Rule::exists('contratacion_contracts', 'id')->whereNull('deleted_at')],
            'contractor_id' => ['required', 'integer', Rule::exists('ftra_contractors', 'id')->where('is_active', true)],
            'document_number' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'upload_token' => [$this->fileRequired ? 'required' : 'nullable', 'uuid'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contract_id' => 'contrato',
            'contractor_id' => 'proveedor',
            'document_number' => 'número de la cuenta de cobro',
            'amount' => 'valor del anticipo',
            'description' => 'descripción',
            'upload_token' => 'cuenta de cobro',
            'comment' => 'comentario',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_token.required' => 'Adjunta la cuenta de cobro.',
        ];
    }
}
