<?php

namespace App\Http\Modules\Facturacion\Request;

use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El residente asocia la factura a su corte o a su orden con los mismos datos en ambos casos: solo se pide el valor
 * si Contabilidad no lo registró. Los soportes (opcionales) se siguen aceptando por compatibilidad.
 * La cotización se radica como anticipo del contrato elegido (target_type = anticipo, target_id = contrato).
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
            'target_type' => ['required', Rule::in([FacturacionInvoice::TARGET_CORTE, FacturacionInvoice::TARGET_ORDEN, FacturacionInvoice::TARGET_ANTICIPO])],
            // Corte, orden o, para la cotización que se radica como anticipo, el contrato
            'target_id' => ['required', 'integer'],
            // Solo si Contabilidad no los registró (lo valida FacturacionService)
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
            'contractor_id' => ['nullable', 'integer', 'exists:ftra_contractors,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Soportes opcionales para corte y orden
            'support_tokens' => ['nullable', 'array', 'max:20'],
            'support_tokens.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'target_type' => 'tipo de asociación',
            'target_id' => 'corte, orden o contrato',
            'amount' => 'valor',
            'contractor_id' => 'proveedor',
            'comment' => 'observación',
            'support_tokens' => 'soportes',
        ];
    }
}
