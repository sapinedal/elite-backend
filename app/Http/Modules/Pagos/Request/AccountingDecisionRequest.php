<?php

namespace App\Http\Modules\Pagos\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contabilidad aprueba (pasa a cuentas por pagar) o devuelve (vuelve a quien carga en Multifox) un registro.
 * Común a cortes, órdenes, anticipos y cuentas de cobro.
 */
class AccountingDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:aprobar,devolver'],
            'comment' => ['nullable', 'required_if:decision,devolver', 'string', 'max:2000'],
            // Valor del documento (cortes y órdenes): lo confirma Contabilidad; obligatorio al aprobar si no lo tiene
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['decision' => 'decisión', 'comment' => 'comentario', 'amount' => 'valor'];
    }

    public function messages(): array
    {
        return ['comment.required_if' => 'Indica el motivo de la devolución para que sepan qué corregir.'];
    }
}
