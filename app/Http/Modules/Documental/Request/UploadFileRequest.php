<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'nullable|string',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:102400', // Máx 100MB por archivo
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Debes adjuntar al menos un archivo para subir.',
            'files.*.max' => 'El tamaño máximo por archivo es de 100MB.',
        ];
    }
}
