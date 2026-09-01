<?php

namespace App\Http\Modules\Configuracion\Service;

use App\Http\Modules\Configuracion\Models\Tower;
use Illuminate\Database\Eloquent\Collection;

class TowerService
{
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
        ]);
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
