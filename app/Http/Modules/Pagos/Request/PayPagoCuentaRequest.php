<?php

namespace App\Http\Modules\Pagos\Request;

use Illuminate\Foundation\Http\FormRequest;

class PayPagoCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'upload_token' => ['nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'valor pagado', 'payment_date' => 'fecha de pago', 'reference' => 'referencia'];
    }
}
