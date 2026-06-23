<?php

namespace App\Http\Modules\Ftra\Request;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFtraRecordRequest extends FormRequest
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
            'contractor_id' => 'sometimes|required|exists:ftra_contractors,id',
            'format_id' => 'sometimes|required|exists:ftra_formats,id',
            'responsable_id' => 'sometimes|required|exists:residentes,id',
            'piso' => 'sometimes|required|string|max:50',
            'apartamento' => 'sometimes|required|string|max:50',
            'resultado_inspeccion' => 'sometimes|required|string|in:Rechazado,Recibido con observación,Recibido a satisfacción',
            'orden_aseo' => 'sometimes|required|string|in:Aprobado,Rechazado',
            'observations' => 'required_if:resultado_inspeccion,Recibido con observación|nullable|string|max:2000',
            'is_completed' => 'sometimes|required|boolean',
            'status' => 'sometimes|required|string|in:Registrada,Seguimiento,Aprobada,Rechazada',
            'contractor_signature' => 'sometimes|nullable|string',
            'resident_signature' => 'sometimes|nullable|string',
            'director_signature' => 'sometimes|nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }
}
