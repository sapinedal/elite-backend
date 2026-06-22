<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Models\FtraContractor;
use Illuminate\Support\Facades\DB;

class ContractorService
{
    /**
     * Obtiene y filtra la lista de contratistas.
     */
    public function listContractors(array $filters = [])
    {
        $query = FtraContractor::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('nit', 'like', '%' . $search . '%');
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = intval($filters['per_page'] ?? 15);
        
        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Crea un nuevo contratista.
     */
    public function createContractor(array $data): FtraContractor
    {
        return DB::transaction(function () use ($data) {
            $data['is_active'] = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;
            return FtraContractor::create($data);
        });
    }

    /**
     * Actualiza un contratista existente.
     */
    public function updateContractor(FtraContractor $contractor, array $data): FtraContractor
    {
        return DB::transaction(function () use ($contractor, $data) {
            if (isset($data['is_active'])) {
                $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
            $contractor->update($data);
            return $contractor;
        });
    }

    /**
     * Elimina un contratista.
     */
    public function deleteContractor(FtraContractor $contractor): void
    {
        DB::transaction(function () use ($contractor) {
            $contractor->delete();
        });
    }
}
