<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class SetFolderProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_path' => 'required|string|min:1|max:1024',
            // null = carpeta transversal (visible en todos los proyectos)
            'project_id' => 'present|nullable|integer|exists:projects,id',
        ];
    }

    public function messages(): array
    {
        return [
            'folder_path.required' => 'El proyecto se asigna a una carpeta específica, no a la raíz.',
            'project_id.exists' => 'El proyecto indicado no existe.',
        ];
    }
}
