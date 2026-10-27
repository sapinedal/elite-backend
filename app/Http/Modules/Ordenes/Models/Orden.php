<?php

namespace App\Http\Modules\Ordenes\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orden extends Model
{
    use SoftDeletes;

    protected $table = 'ordenes';

    protected $fillable = [
        'code',
        'description',
        'project_id',
        'tower_id',
        'chapter',
        'activity',
        'status',
        'current_stage',
        'current_step',
        'returned_stage',
        'storage_path',
        'created_by',
        'approved_at',
        'completed_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(OrdenHistory::class, 'orden_id')->orderBy('id');
    }

    public function documents()
    {
        return $this->hasMany(OrdenDocument::class, 'orden_id')->orderBy('id');
    }

    /**
     * Limita a los proyectos a los que el usuario tiene acceso (todos si tiene proyectos.ver_todos).
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $projectIds = $user->accessibleProjectIds();

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }
}
