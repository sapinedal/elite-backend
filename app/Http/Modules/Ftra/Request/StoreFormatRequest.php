<?php

namespace App\Http\Modules\Ftra\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:ftra_formats,code',
            'version' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'pdf_file' => 'required|file|mimes:pdf|max:10240', // PDF required, max 10MB
            'is_active' => 'sometimes|boolean',
        ];
    }
}
