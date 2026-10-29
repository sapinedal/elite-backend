<?php

namespace App\Http\Modules\CuentasCobro\Request;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCuentaCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:aprobar,devolver'],
            'comment' => ['required_if:decision,devolver', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required_if' => 'Indica el motivo de la devolución.',
        ];
    }
}
