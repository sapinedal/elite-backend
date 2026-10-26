<?php

namespace App\Http\Modules\Cortes\Request;

use App\Http\Modules\Cortes\Models\CorteInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cargue de una factura a un corte aprobado.
 * Nota crédito y factura de reemplazo exigen el documento que afectan y el motivo del cambio.
 */
class StoreCorteInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('document_type');
        $affectsAnother = CorteInvoice::requiresRelatedDocument($type);

        return [
            'document_type' => ['required', Rule::in(array_keys(CorteInvoice::DOCUMENT_TYPES))],
            'invoice_number' => [
                'required', 'string', 'max:100',
                // El mismo número no se repite dentro del mismo tipo de documento
                Rule::unique('cortes_invoices', 'invoice_number')->where('document_type', $type),
            ],
            'related_document' => [$affectsAnother ? 'required' : 'nullable', 'string', 'max:100', 'different:invoice_number'],
            'observation' => [$affectsAnother ? 'required' : 'nullable', 'string', 'max:2000'],
            'upload_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'invoice_number' => 'número de factura',
            'related_document' => 'documento que reemplaza o afecta',
            'observation' => 'motivo / observación del cambio',
            'upload_token' => 'archivo de la factura',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.unique' => 'Ya existe un documento de ese tipo con el mismo número.',
            'related_document.required' => 'Indica el número de la factura que se afecta o reemplaza.',
            'related_document.different' => 'El documento que se afecta debe ser distinto al número de este documento.',
            'observation.required' => 'Indica el motivo del cambio.',
            'upload_token.required' => 'Adjunta el archivo de la factura.',
        ];
    }
}
