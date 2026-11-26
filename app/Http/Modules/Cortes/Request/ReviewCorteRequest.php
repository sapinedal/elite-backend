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
            // rechazar_continuar: el Interventor no aprueba pero deja que el corte siga (Rechazado pero aprobado)
            'decision' => ['required', 'in:aprobar,devolver,rechazar_continuar'],
            // Al devolver (o rechazar) el residente necesita saber qué corregir
            'comment' => ['required_if:decision,devolver', 'required_if:decision,rechazar_continuar', 'nullable', 'string', 'max:2000'],
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
            'comment.required_if' => 'Indica el motivo de la devolución o del rechazo.',
        ];
    }
}
