<?php

namespace App\Http\Modules\Contratacion\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Juridica\Models\ContractType;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratacionContract extends Model
{
    use SoftDeletes;

    protected $table = 'contratacion_contracts';

    public const STATUS_POR_INICIAR = 'Por iniciar';
    public const STATUS_EN_EJECUCION = 'En ejecución';
    public const STATUS_SUSPENDIDO = 'Suspendido';
    public const STATUS_TERMINADO = 'Terminado';
    public const STATUS_LIQUIDADO = 'Liquidado';

    public const STATUSES = [
        self::STATUS_POR_INICIAR,
        self::STATUS_EN_EJECUCION,
        self::STATUS_SUSPENDIDO,
        self::STATUS_TERMINADO,
        self::STATUS_LIQUIDADO,
    ];

    protected $fillable = [
        'project_id',
        'tower_id',
        'contract_type_id',
        'contract_code',
        'consecutive',
        'contractor_name',
        'object',
        'signed_at',
        'start_act_date',
        'estimated_end_date',
        'status',
        's3_path',
        'created_by',
    ];

    protected $casts = [
        'signed_at' => 'date:Y-m-d',
        'start_act_date' => 'date:Y-m-d',
        'estimated_end_date' => 'date:Y-m-d',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function policies()
    {
        return $this->hasMany(ContratacionPolicy::class, 'contract_id')->orderBy('expires_at');
    }

    public function documents()
    {
        return $this->hasMany(ContratacionDocument::class, 'contract_id')->orderBy('folder')->orderBy('name');
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
