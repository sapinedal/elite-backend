<?php

namespace App\Http\Modules\Ftra\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revisión del Supervisor o del Director de Obra:
 *  - approve: recibe a satisfacción con su firma (comentario opcional).
 *  - return: devuelve al residente con el motivo (obligatorio).
 */
class ReviewFtraRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $returning = $this->routeIs('*.return') || str_ends_with($this->path(), '/return');

        return $returning
            ? ['comment' => 'required|string|min:5|max:2000']
            : ['signature' => 'required|string', 'comment' => 'nullable|string|max:2000'];
    }

    public function attributes(): array
    {
        return ['comment' => 'motivo', 'signature' => 'firma'];
    }
}
