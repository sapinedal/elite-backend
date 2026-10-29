<?php

namespace App\Http\Modules\CuentasCobro\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paula indica si la cuenta de cobro requiere cargue en Multifox.
 */
class MultifoxDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requires_multifox' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
