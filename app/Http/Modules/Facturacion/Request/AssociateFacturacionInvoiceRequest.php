<?php

namespace App\Http\Modules\Facturacion\Request;

use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El residente asocia la factura a su corte o a su orden. Solo la orden lleva soportes al asociar
 * (capturas de las aprobaciones y entrada de Multifox); el soporte del corte lo carga Paula en Multifox.
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
            // Al corte solo se asocia: el soporte de Multifox lo carga Paula al confirmar (queda en la carpeta del corte).
            // A la orden se siguen adjuntando los soportes de la entrada.
            'support_tokens' => ['required_if:target_type,' . FacturacionInvoice::TARGET_ORDEN, 'nullable', 'array', 'min:1', 'max:20'],
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
            'support_tokens.required_if' => 'Adjunta los soportes de la orden: capturas de las aprobaciones y la entrada de Multifox.',
            'support_tokens.min' => 'Adjunta los soportes de la orden: capturas de las aprobaciones y la entrada de Multifox.',
        ];
    }
}
