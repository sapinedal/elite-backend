<?php

namespace App\Http\Modules\Anticipos\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contabilidad carga el anticipo generado en Multifox: su número y el soporte que genera Multifox.
 */
class RegisterMultifoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'multifox_number' => ['required', 'string', 'max:100'],
            'upload_token' => ['required', 'uuid'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'multifox_number' => 'número del anticipo en Multifox',
            'upload_token' => 'soporte de Multifox',
            'comment' => 'observación',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_token.required' => 'Adjunta el soporte del anticipo generado en Multifox.',
        ];
    }
}
