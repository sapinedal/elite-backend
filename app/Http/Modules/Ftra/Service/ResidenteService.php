<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Models\Residente;
use Illuminate\Support\Facades\DB;

class ResidenteService
{
    /**
     * Obtiene y filtra la lista de residentes.
     */
    public function listResidentes(array $filters = [])
    {
        $query = Residente::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('role', 'like', '%' . $search . '%');
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Si se pide todos los registros sin paginación (útil para dropdowns)
        if (isset($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN)) {
            return $query->orderBy('name', 'asc')->get();
        }

        $perPage = intval($filters['per_page'] ?? 15);
        
        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Crea un nuevo residente.
     */
    public function createResidente(array $data): Residente
    {
        return DB::transaction(function () use ($data) {
            $data['is_active'] = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;
            return Residente::create($data);
        });
    }

    /**
     * Actualiza un residente existente.
     */
    public function updateResidente(Residente $residente, array $data): Residente
    {
        return DB::transaction(function () use ($residente, $data) {
            if (isset($data['is_active'])) {
                $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
            $residente->update($data);
            return $residente;
        });
    }

    /**
     * Elimina un residente.
     */
    public function deleteResidente(Residente $residente): void
    {
        DB::transaction(function () use ($residente) {
            $residente->delete();
        });
    }
}
