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
            'new_name' => 'required|string|max:150|regex:/^[^\\/\\\\:*?"<>|]+$/',
            'type' => 'required|string|in:file,folder',
        ];
    }
}
