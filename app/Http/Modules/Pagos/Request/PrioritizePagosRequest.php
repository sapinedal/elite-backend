<?php

namespace App\Http\Modules\Pagos\Request;

use App\Http\Modules\Pagos\Service\PagoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrioritizePagosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'priority' => ['required', Rule::in(array_keys(PagoService::PRIORITIES))],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'cuentas', 'priority' => 'prioridad', 'scheduled_for' => 'fecha de pago'];
    }
}
