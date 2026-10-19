<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class RenameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_path' => 'required|string',
            // Formato de arreglo: en formato texto el "|" de la expresión se toma como separador de reglas
            'new_name' => ['required', 'string', 'max:150', 'regex:/^[^\\/\\\\:*?"<>|]+$/'],
            'type' => 'required|string|in:file,folder',
        ];
    }

    public function messages(): array
    {
        return [
            'new_name.required' => 'El nuevo nombre es obligatorio.',
            'new_name.regex' => 'El nombre no puede contener caracteres reservados como / \\ : * ? " < > |.',
        ];
    }
}
