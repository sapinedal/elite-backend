<?php

namespace App\Http\Modules\Ordenes\Request;

use Illuminate\Foundation\Http\FormRequest;

class ReviewOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:aprobar,devolver'],
            // Al devolver el residente necesita saber qué corregir
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
