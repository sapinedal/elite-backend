<?php

namespace App\Http\Modules\Cortes\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Corte extends Model
{
    use SoftDeletes;

    protected $table = 'cortes';

    protected $fillable = [
        'code',
        'project_id',
        'tower_id',
        'contractor_id',
        'contract_id',
        'status',
        'current_stage',
        'returned_stage',
        'storage_path',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'created_by',
        'approved_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(CorteHistory::class, 'corte_id')->orderBy('id');
    }

    public function invoices()
    {
        return $this->hasMany(CorteInvoice::class, 'corte_id')->orderByDesc('id');
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
