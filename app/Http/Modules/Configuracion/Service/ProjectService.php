<?php

namespace App\Http\Modules\Configuracion\Service;

use App\Http\Modules\Configuracion\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectService
{
    /**
     * Get all active and available projects
     */
    public function getAllProjects(): Collection
    {
        return Project::orderBy('id', 'asc')->get();
    }

    /**
     * Get single project by ID
     */
    public function getProjectById(int $id): ?Project
    {
        return Project::find($id);
    }

    /**
     * Create a new project
     */
    public function createProject(array $data): Project
    {
        return Project::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'total_budget' => $data['total_budget'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing project
     */
    public function updateProject(int $id, array $data): Project
    {
        $project = Project::findOrFail($id);
        $project->update([
            'code' => $data['code'] ?? $project->code,
            'name' => $data['name'] ?? $project->name,
            'subtitle' => $data['subtitle'] ?? $project->subtitle,
            'description' => $data['description'] ?? $project->description,
            'total_budget' => $data['total_budget'] ?? $project->total_budget,
            'is_active' => isset($data['is_active']) ? $data['is_active'] : $project->is_active,
        ]);

        return $project->fresh();
    }

    /**
     * Delete a project by ID
     */
    public function deleteProject(int $id): bool
    {
        $project = Project::findOrFail($id);
        return $project->delete();
    }
}
