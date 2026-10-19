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
            // El tamaño máximo y las extensiones se validan en DocumentalSettingsService (configurables desde la app)
            'files.*' => 'required|file',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Debes adjuntar al menos un archivo para subir.',
            'files.*.uploaded' => 'Uno de los archivos supera el límite de subida del servidor.',
        ];
    }
}
