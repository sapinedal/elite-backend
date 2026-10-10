<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreAreaPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_id' => 'required|exists:areas,id',
            'folder_path' => 'nullable|string',
            'can_read' => 'sometimes|boolean',
            'can_upload' => 'sometimes|boolean',
            'can_create_folder' => 'sometimes|boolean',
            'can_delete' => 'sometimes|boolean',
        ];
    }
}
