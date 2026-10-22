<?php

namespace App\Http\Modules\Users\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Configuracion\Models\Project;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * Spatie Permission guard name.
     *
     * @var string
     */
    protected string $guard_name = 'web';

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_active'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'document',
        'area_id',
        'position_id',
        'email',
        'password',  // se elimina roles y se deja como un solo usuario
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->deleted_at);
    }

    public function area()
    {
        return $this->belongsTo(\App\Http\Modules\Configuracion\Models\Area::class);
    }

    public function position()
    {
        return $this->belongsTo(\App\Http\Modules\Configuracion\Models\Position::class);
    }

    public function kpis()
    {
        return $this->hasMany(KPI::class)->orderBy('stage', 'asc');
    }

    /**
     * Proyectos a los que el usuario está asignado.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user')->withTimestamps();
    }

    /**
     * Gerencia y administración ven todos los proyectos sin necesidad de estar asignados.
     */
    public function canSeeAllProjects(): bool
    {
        return $this->can('proyectos.ver_todos');
    }

    /**
     * IDs de los proyectos que el usuario puede consultar.
     * Devuelve null cuando no hay restricción (puede ver todos).
     *
     * @return list<int>|null
     */
    public function accessibleProjectIds(): ?array
    {
        if ($this->canSeeAllProjects()) {
            return null;
        }

        return $this->projects()->pluck('projects.id')->all();
    }

    public function canAccessProject(int $projectId): bool
    {
        return $this->canSeeAllProjects() || $this->projects()->whereKey($projectId)->exists();
    }
}
