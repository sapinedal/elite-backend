<?php

namespace App\Http\Modules\CuentasCobro\Request;

use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveCuentaCobroTipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_attachment' => ['required', 'boolean'],
            'attachment_hint' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->input('name')))]);
        }
    }

    /**
     * El nombre no se repite sin importar mayúsculas ("SST" y "sst" son el mismo tipo).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $name = mb_strtolower((string) $this->input('name'));
                $exists = CuentaCobroTipo::query()
                    ->whereRaw('LOWER(name) = ?', [$name])
                    ->when($this->route('tipo'), fn ($q, $tipo) => $q->whereKeyNot($tipo->id))
                    ->exists();

                if ($name !== '' && $exists) {
                    $validator->errors()->add('name', 'Ya existe un tipo de cuenta de cobro con ese nombre.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'requires_attachment' => 'requiere adjunto',
            'attachment_hint' => 'qué adjuntar',
        ];
    }
}
