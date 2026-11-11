<?php

namespace App\Http\Modules\Ftra\Request;

use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Ftra\Request\Concerns\ValidatesFtraLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFtraRecordRequest extends FormRequest
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
            'contractor_id' => 'sometimes|required|exists:ftra_contractors,id',
            'format_id' => 'sometimes|required|exists:ftra_formats,id',
            'tower_id' => 'sometimes|required|integer|exists:towers,id',
            'piso' => 'sometimes|required|integer|min:1',
            'apartamento' => 'sometimes|required|string|max:10',
            'resultado_inspeccion' => 'sometimes|required|string|in:Rechazado,Recibido con observación,Recibido a satisfacción',
            'orden_aseo' => 'sometimes|required|string|in:Aprobado,Rechazado',
            'observations' => 'required_if:resultado_inspeccion,Recibido con observación|nullable|string|max:2000',
            'is_completed' => 'sometimes|required|boolean',
            'contractor_signature' => 'sometimes|nullable|string',
            'resident_signature' => 'sometimes|nullable|string',
            // Ajustes realizados (al reenviar un FTRA devuelto)
            'comment' => 'nullable|string|max:2000',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (!$this->hasAny(['tower_id', 'piso', 'apartamento'])) {
                    return;
                }
                $record = $this->route('record');
                $this->validateLocation(
                    $validator,
                    $record?->project_id,
                    $this->input('tower_id', $record?->tower_id),
                    $this->input('piso', $record?->piso),
                    $this->input('apartamento', $record?->apartamento),
                );
            },
        ];
    }
}
