<?php

namespace App\Http\Modules\Juridica\Service;

use App\Http\Modules\Juridica\Models\ContractType;
use Illuminate\Database\Eloquent\Collection;

class ContractTypeService
{
    private array $defaultTypes = [
        'Mano de Obra',
        'Suministro e Instalación',
        'Obra Civil',
        'Redes Eléctricas',
        'Redes Hidrosanitarias',
        'Redes Exteriores',
        'Impermeabilización',
        'Equipos Especiales',
        'Acabados Varios',
    ];

    public function getAllContractTypes(): Collection
    {
        if (ContractType::count() === 0) {
            foreach ($this->defaultTypes as $name) {
                ContractType::create(['name' => $name, 'is_active' => true]);
            }
        }

        return ContractType::where('is_active', true)->orderBy('name', 'asc')->get();
    }

    public function createContractType(array $data): ContractType
    {
        return ContractType::create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateContractType(int $id, array $data): ContractType
    {
        $type = ContractType::findOrFail($id);
        $type->update([
            'name' => $data['name'] ?? $type->name,
            'code' => $data['code'] ?? $type->code,
            'description' => $data['description'] ?? $type->description,
            'is_active' => isset($data['is_active']) ? $data['is_active'] : $type->is_active,
        ]);

        return $type->fresh();
    }

    public function deleteContractType(int $id): bool
    {
        $type = ContractType::findOrFail($id);
        return $type->delete();
    }
}
