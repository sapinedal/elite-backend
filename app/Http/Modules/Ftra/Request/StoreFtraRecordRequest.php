<?php

namespace App\Http\Modules\Ftra\Request;

use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Ftra\Request\Concerns\ValidatesFtraLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFtraRecordRequest extends FormRequest
{
    use ValidatesFtraLocation;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_completed')) {
            $this->merge([
                'is_completed' => filter_var($this->is_completed, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'contractor_id' => 'required|exists:ftra_contractors,id',
            'format_id' => 'required|exists:ftra_formats,id',
            // El responsable es el usuario logueado: no se recibe del formulario
            'tower_id' => 'required|integer|exists:towers,id',
            'piso' => 'required|integer|min:1',
            'apartamento' => 'required|string|max:10',
            'resultado_inspeccion' => 'required|string|in:Rechazado,Recibido con observación,Recibido a satisfacción',
            'orden_aseo' => 'required|string|in:Aprobado,Rechazado',
            'observations' => 'required_if:resultado_inspeccion,Recibido con observación|nullable|string|max:2000',
            'is_completed' => 'sometimes|required|boolean',
            'contractor_signature' => 'nullable|string',
            'resident_signature' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateLocation(
                $validator,
                $this->attributes->get(ResolveActiveProject::ATTRIBUTE),
                $this->input('tower_id'),
                $this->input('piso'),
                $this->input('apartamento'),
            ),
        ];
    }
}
