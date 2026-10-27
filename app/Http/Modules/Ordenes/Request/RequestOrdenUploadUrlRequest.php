<?php

namespace App\Http\Modules\Ordenes\Request;

use Illuminate\Foundation\Http\FormRequest;

class RequestOrdenUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            // El límite real lo valida DirectUploadService con config('ordenes.max_upload_mb')
            'file_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
