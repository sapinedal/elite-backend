<?php

namespace App\Rules;

use App\Http\Modules\Contratacion\Models\ContratacionContract;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El contrato elegido debe ser del proveedor elegido (campo contractor_id de la misma petición).
 * Los contratos que aún no tienen proveedor asignado se aceptan con cualquiera.
 */
class ContractBelongsToContractor implements ValidationRule, DataAwareRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contractorId = $this->data['contractor_id'] ?? null;
        if (!$value || !$contractorId) {
            return;
        }

        $owner = ContratacionContract::whereKey($value)->value('contractor_id');

        if ($owner !== null && (int) $owner !== (int) $contractorId) {
            $fail('El contrato no pertenece al proveedor seleccionado.');
        }
    }
}
