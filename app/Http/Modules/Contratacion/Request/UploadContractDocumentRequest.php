<?php

namespace App\Http\Modules\Contratacion\Request;

use App\Http\Modules\Contratacion\Service\ContractDocumentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadContractDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder' => ['required', Rule::in(ContractDocumentCatalog::folderKeys())],
            // En documentación legal se indica qué documento es (CC, RUT o Cámara de Comercio)
            'document_type' => [
                Rule::requiredIf($this->input('folder') === ContractDocumentCatalog::FOLDER_LEGAL),
                'nullable',
                Rule::in(array_keys(ContractDocumentCatalog::LEGAL_DOCUMENTS)),
            ],
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
        ];
    }

    public function attributes(): array
    {
        return [
            'folder' => 'carpeta',
            'document_type' => 'tipo de documento legal',
            'files' => 'archivos',
            'files.*' => 'archivo',
        ];
    }
}
