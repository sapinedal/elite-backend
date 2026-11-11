<?php

namespace App\Http\Modules\Configuracion\Service;

use App\Http\Modules\Configuracion\Models\Tower;
use Illuminate\Database\Eloquent\Collection;

class TowerService
{
    /** Reglas de la distribución de apartamentos (hasta 99 por piso por la numeración piso × 100). */
    public const LAYOUT_RULES = [
        'floors' => 'nullable|integer|min:1|max:200|required_with:apartments_per_floor',
        'apartments_per_floor' => 'nullable|integer|min:1|max:99|required_with:floors',
    ];

    public function getTowersByProject(int $projectId): Collection
    {
        return Tower::where('project_id', $projectId)->orderBy('id', 'asc')->get();
    }

    public function createTower(int $projectId, array $data): Tower
    {
        return Tower::create([
            'project_id' => $projectId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'floors' => $data['floors'] ?? null,
            'apartments_per_floor' => $data['apartments_per_floor'] ?? null,
        ]);
    }

    public function updateTower(Tower $tower, array $data): Tower
    {
        $tower->update($data);

        return $tower->fresh();
    }

    public function deleteTower(int $id): bool
    {
        $tower = Tower::find($id);
        if ($tower) {
            return $tower->delete();
        }
        return false;
    }
}
