<?php

namespace App\Http\Modules\CuentasCobro\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paula carga la cuenta de cobro en Multifox: número obligatorio y soporte opcional.
 */
class RegisterCuentaMultifoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'multifox_number' => ['required', 'string', 'max:100'],
            'upload_token' => ['nullable', 'uuid'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'multifox_number' => 'número en Multifox',
            'upload_token' => 'soporte de Multifox',
        ];
    }
}
