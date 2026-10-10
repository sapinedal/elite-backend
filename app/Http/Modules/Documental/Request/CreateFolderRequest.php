<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class CreateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_path' => 'nullable|string',
            'folder_name' => 'required|string|max:100|regex:/^[^\\/\\\\:*?"<>|]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'folder_name.required' => 'El nombre de la carpeta es obligatorio.',
            'folder_name.regex' => 'El nombre de la carpeta no puede contener caracteres reservados como / \ : * ? " < > |.',
        ];
    }
}
