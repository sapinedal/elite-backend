<?php

namespace App\Http\Modules\Configuracion\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Users\Models\User;
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
     * Active projects the user can access (project selector)
     */
    public function getAccessibleProjects(User $user): Collection
    {
        $projectIds = $user->accessibleProjectIds();

        return Project::where('is_active', true)
            ->when($projectIds !== null, fn ($query) => $query->whereIn('id', $projectIds))
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name', 'subtitle']);
    }

    /**
     * Users assigned to a project
     */
    public function getProjectUsers(int $id): Collection
    {
        return Project::findOrFail($id)
            ->users()
            ->orderBy('name', 'asc')
            ->get(['users.id', 'users.name', 'users.email', 'users.area_id']);
    }

    /**
     * Replace the users assigned to a project
     */
    public function syncProjectUsers(int $id, array $userIds): Collection
    {
        Project::findOrFail($id)->users()->sync($userIds);

        return $this->getProjectUsers($id);
    }

    /**
     * Delete a project by ID
     */
    public function deleteProject(int $id): bool
    {
        $project = Project::findOrFail($id);

        // tasks.project_id es restrictOnDelete: se avisa antes de que falle la llave foránea
        if ($project->tasks()->withTrashed()->exists()) {
            throw new \RuntimeException('El proyecto tiene tareas registradas en la bitácora.');
        }

        return $project->delete();
    }
}
