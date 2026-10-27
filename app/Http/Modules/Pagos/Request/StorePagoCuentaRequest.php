<?php

namespace App\Http\Modules\Pagos\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuenta por pagar registrada a mano (nómina, aportes, fiduciaria, cuentas de cobro...).
 * El proveedor puede elegirse de la lista o escribirse (NIT y nombre).
 */
class StorePagoCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'frente' => ['required', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer', 'exists:ftra_contractors,id'],
            'supplier_nit' => ['nullable', 'string', 'max:30'],
            'supplier_name' => ['required_without:contractor_id', 'nullable', 'string', 'max:255'],
            'document_number' => ['required', 'string', 'max:100'],
            'concept' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'frente' => 'frente',
            'supplier_name' => 'proveedor',
            'document_number' => 'número del documento',
            'concept' => 'concepto',
            'amount' => 'valor',
        ];
    }

    public function messages(): array
    {
        return ['supplier_name.required_without' => 'Selecciona el proveedor o escribe su nombre.'];
    }
}
