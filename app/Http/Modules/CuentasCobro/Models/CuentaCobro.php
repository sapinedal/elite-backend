<?php

namespace App\Http\Modules\CuentasCobro\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cuenta de cobro: aprobaciones del Director de Obra y Gerencia, decisión de Paula sobre Multifox y cuadro de pagos.
 */
class CuentaCobro extends Model
{
    use SoftDeletes;

    protected $table = 'cuentas_cobro';

    protected $fillable = [
        'code',
        'tipo_id',
        'project_id',
        'tower_id',
        'contract_id',
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
        'requires_multifox',
        'multifox_decided_by',
        'multifox_decided_at',
        'multifox_number',
        'multifox_file_name',
        'multifox_file_path',
        'multifox_file_size',
        'multifox_file_mime',
        'multifox_by',
        'multifox_at',
        'created_by',
        'approved_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'multifox_file_size' => 'integer',
        'requires_multifox' => 'boolean',
        'multifox_decided_at' => 'datetime',
        'multifox_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tipo()
    {
        return $this->belongsTo(CuentaCobroTipo::class, 'tipo_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function multifoxDecider()
    {
        return $this->belongsTo(User::class, 'multifox_decided_by');
    }

    public function multifoxUser()
    {
        return $this->belongsTo(User::class, 'multifox_by');
    }

    public function adjuntos()
    {
        return $this->hasMany(CuentaCobroAdjunto::class, 'cuenta_cobro_id')->orderBy('id');
    }

    public function history()
    {
        return $this->hasMany(CuentaCobroHistory::class, 'cuenta_cobro_id')->orderBy('id');
    }

    /**
     * "CC-0004 · SST"
     */
    public function reference(): string
    {
        return $this->code . ($this->tipo ? " · {$this->tipo->name}" : '');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $projectIds = $user->accessibleProjectIds();

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }
}
