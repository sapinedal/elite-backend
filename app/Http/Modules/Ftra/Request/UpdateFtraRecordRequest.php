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
            'observations' => 'nullable|string|max:2000',
            'is_completed' => 'sometimes|required|boolean',
            'status' => 'sometimes|required|string|in:Registrada,Seguimiento,Aprobada,Rechazada',
            'contractor_signature' => 'sometimes|nullable|string',
            'resident_signature' => 'sometimes|nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }
}
