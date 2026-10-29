<?php

namespace App\Http\Modules\Facturacion\Request;

use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cargue (POST, con archivo) o corrección (PUT, sin archivo) de una factura del mes por Contabilidad.
 * Nota crédito y factura de reemplazo exigen el documento que afectan.
 */
class SaveFacturacionInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $affectsAnother = FacturacionInvoice::requiresRelatedDocument($this->input('document_type'));

        return [
            // El mes define la carpeta del archivo: solo se indica al cargar
            'period' => [$this->isMethod('post') ? 'required' : 'nullable', 'date_format:Y-m'],
            'document_type' => ['required', Rule::in(array_keys(FacturacionInvoice::DOCUMENT_TYPES))],
            'invoice_number' => ['required', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer', 'exists:ftra_contractors,id'],
            // Proveedor como viene en el nombre del archivo, si no está en el catálogo
            'supplier_name' => ['required_without:contractor_id', 'nullable', 'string', 'max:255'],
            'related_document' => [$affectsAnother ? 'required' : 'nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'upload_token' => [$this->isMethod('post') ? 'required' : 'prohibited', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'period' => 'mes',
            'document_type' => 'tipo de documento',
            'invoice_number' => 'número del documento',
            'contractor_id' => 'proveedor',
            'supplier_name' => 'proveedor',
            'related_document' => 'documento que afecta o reemplaza',
            'amount' => 'valor',
            'observation' => 'observación',
            'upload_token' => 'archivo de la factura',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.required_without' => 'Indica el proveedor.',
            'related_document.required' => 'Indica el número de la factura que se afecta o reemplaza.',
            'upload_token.required' => 'Adjunta el archivo de la factura.',
        ];
    }
}
