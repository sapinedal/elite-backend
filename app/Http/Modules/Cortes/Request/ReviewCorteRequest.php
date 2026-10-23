<?php

namespace App\Http\Modules\Cortes\Request;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCorteRequest extends FormRequest
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

    public function attributes(): array
    {
        return [
            'decision' => 'decisión',
            'comment' => 'comentario',
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required_if' => 'Indica el motivo de la devolución.',
        ];
    }
}
