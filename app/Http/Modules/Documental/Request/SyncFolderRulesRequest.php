<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class SyncFolderRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_path' => 'required|string|min:1',
            'rules' => 'present|array',
            'rules.*.role_id' => 'required|integer|exists:roles,id',
            'rules.*.can_read' => 'sometimes|boolean',
            'rules.*.can_upload' => 'sometimes|boolean',
            'rules.*.can_create_folder' => 'sometimes|boolean',
            'rules.*.can_delete' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'folder_path.required' => 'Las restricciones se aplican sobre una carpeta específica, no sobre la raíz.',
            'rules.*.role_id.exists' => 'Uno de los roles seleccionados no existe.',
        ];
    }
}
