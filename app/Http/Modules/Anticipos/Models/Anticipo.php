<?php

namespace App\Http\Modules\Anticipos\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Anticipo de un contrato: cuenta de cobro del residente, aprobaciones y anticipo generado en Multifox.
 */
class Anticipo extends Model
{
    use SoftDeletes;

    protected $table = 'anticipos';

    protected $fillable = [
        'code',
        'contract_id',
        'project_id',
        'tower_id',
        'contractor_id',
        'document_number',
        'amount',
        'description',
        'status',
        'current_stage',
        'returned_stage',
        'storage_path',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'multifox_number',
        'multifox_file_name',
        'multifox_file_path',
        'multifox_file_size',
        'multifox_file_mime',
        'multifox_by',
        'multifox_at',
        'created_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'multifox_file_size' => 'integer',
        'multifox_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function multifoxUser()
    {
        return $this->belongsTo(User::class, 'multifox_by');
    }

    public function history()
    {
        return $this->hasMany(AnticipoHistory::class, 'anticipo_id')->orderBy('id');
    }

    /**
     * "ANT-0003 · Contrato 2759"
     */
    public function reference(): string
    {
        $contract = $this->contract?->contract_code;

        return $this->code . ($contract ? " · Contrato {$contract}" : '');
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
