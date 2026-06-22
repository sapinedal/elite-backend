<?php

namespace App\Http\Modules\Ftra\Request;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormatRequest extends FormRequest
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
        $format = $this->route('format');
        $formatId = is_object($format) ? $format->id : $format;

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:ftra_formats,code,' . ($formatId ?? 'NULL'),
            'version' => 'sometimes|required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240', // PDF optional on update
            'is_active' => 'sometimes|boolean',
        ];
    }
}
