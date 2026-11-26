<?php

namespace App\Http\Modules\CuentasCobro\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cargue ERP o liquidación ERP de una cuenta de cobro directa.
 */
class StoreCuentaCobroDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'upload_token' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'number' => 'número del documento',
            'observation' => 'observación',
            'upload_token' => 'archivo',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_token.required' => 'Adjunta el archivo del documento.',
        ];
    }
}
