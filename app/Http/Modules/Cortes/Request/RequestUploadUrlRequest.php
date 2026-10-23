<?php

namespace App\Http\Modules\Cortes\Request;

use Illuminate\Foundation\Http\FormRequest;

class RequestUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            // El límite real (1 GB por defecto) lo valida CorteStorageService con config('cortes.max_upload_mb')
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file_name' => 'nombre del archivo',
            'file_size' => 'tamaño del archivo',
        ];
    }
}
