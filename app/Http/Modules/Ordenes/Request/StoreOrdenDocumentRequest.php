<?php

namespace App\Http\Modules\Ordenes\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:100'],
            'observation' => ['nullable', 'string', 'max:2000'],
            // Solo en la factura del proveedor (lo exige OrdenService): pasan a cuentas por pagar
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
            'contractor_id' => ['nullable', 'integer', 'exists:ftra_contractors,id'],
            'upload_token' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'number' => 'número del documento',
            'observation' => 'observación',
            'amount' => 'valor',
            'contractor_id' => 'proveedor',
            'upload_token' => 'archivo',
        ];
    }

    public function messages(): array
    {
        return [
            'upload_token.required' => 'Adjunta el archivo del documento.',
        ];
    }
}
