<?php

namespace App\Http\Modules\Documental\Request;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_upload_mb' => 'required|integer|min:1|max:5120',
            'extension_mode' => 'required|in:blocklist,allowlist',
            'blocked_extensions' => 'present|array',
            'blocked_extensions.*' => 'string|max:15',
            'allowed_extensions' => 'required_if:extension_mode,allowlist|array',
            'allowed_extensions.*' => 'string|max:15',
            'max_folder_operation_files' => 'sometimes|integer|min:1|max:20000',
        ];
    }

    public function messages(): array
    {
        return [
            'max_upload_mb.min' => 'El tamaño máximo debe ser de al menos 1 MB.',
            'max_upload_mb.max' => 'El tamaño máximo no puede superar 5120 MB (5 GB).',
            'max_folder_operation_files.max' => 'El límite de archivos por operación no puede superar 20000.',
            'allowed_extensions.required_if' => 'En modo "solo permitidas" debes indicar al menos una extensión.',
        ];
    }
}
