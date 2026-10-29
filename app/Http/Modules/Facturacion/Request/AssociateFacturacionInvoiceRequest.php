<?php

namespace App\Http\Modules\Facturacion\Request;

use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El residente asocia la factura a su corte o a su orden y adjunta los soportes
 * (capturas de las aprobaciones, corte o entrada generada en Multifox).
 */
class AssociateFacturacionInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in([FacturacionInvoice::TARGET_CORTE, FacturacionInvoice::TARGET_ORDEN])],
            'target_id' => ['required', 'integer'],
            // Solo si Contabilidad no los registró (lo valida FacturacionService)
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
            'contractor_id' => ['nullable', 'integer', 'exists:ftra_contractors,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'support_tokens' => ['required', 'array', 'min:1', 'max:20'],
            'support_tokens.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'target_type' => 'tipo de asociación',
            'target_id' => 'corte u orden',
            'amount' => 'valor',
            'contractor_id' => 'proveedor',
            'comment' => 'observación',
            'support_tokens' => 'soportes',
        ];
    }

    public function messages(): array
    {
        return [
            'support_tokens.required' => 'Adjunta los soportes: capturas de las aprobaciones y el corte o entrada de Multifox.',
            'support_tokens.min' => 'Adjunta los soportes: capturas de las aprobaciones y el corte o entrada de Multifox.',
        ];
    }
}
