<?php

namespace App\Http\Modules\Cortes\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paula confirma la carga de la factura en Multifox y adjunta el soporte (corte o entrada generada en
 * Multifox, capturas de aprobaciones). El soporte queda en la carpeta del corte.
 */
class ConfirmMultifoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'upload_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['upload_token' => 'soporte de Multifox'];
    }

    public function messages(): array
    {
        return ['upload_token.required' => 'Adjunta el soporte de Multifox.'];
    }
}
