<?php

namespace App\Http\Modules\Ftra\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreFtraRecordRequest extends FormRequest
{
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
            'responsable_id' => 'required|exists:residentes,id',
            'piso' => 'required|string|max:50',
            'apartamento' => 'required|string|max:50',
            'resultado_inspeccion' => 'required|string|in:Rechazado,Recibido con observación,Recibido a satisfacción',
            'orden_aseo' => 'required|string|in:Aprobado,Rechazado',
            'observations' => 'required_if:resultado_inspeccion,Recibido con observación|nullable|string|max:2000',
            'is_completed' => 'sometimes|required|boolean',
            'status' => 'sometimes|string|in:Registrada,Seguimiento,Aprobada,Rechazada',
            'contractor_signature' => 'nullable|string',
            'resident_signature' => 'nullable|string',
            'director_signature' => 'nullable|string',
            'supervisor_signature' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }
}
