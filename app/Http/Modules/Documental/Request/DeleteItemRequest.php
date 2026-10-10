<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class DeleteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string',
            'type' => 'required|string|in:file,folder',
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'La ruta del elemento a eliminar es obligatoria.',
            'type.in' => 'El tipo de elemento debe ser "file" o "folder".',
        ];
    }
}
