<?php

namespace App\Http\Modules\Ftra\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FtraRecord extends Model
{
    protected $table = 'ftra_records';

    protected $fillable = [
        'project_id',
        'tower_id',
        'contractor_id',
        'format_id',
        'observations',
        'is_completed',
        'status',
        'registered_by_id',
        'contractor_signature',
        'resident_signature',
        'responsable_user_id',
        'resultado_inspeccion',
        'orden_aseo',
        'piso',
        'apartamento',
        'director_signature',
        'supervisor_signature',
        'supervisor_user_id',
        'supervisor_at',
        'director_user_id',
        'director_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'supervisor_at' => 'datetime',
        'director_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Limita a los proyectos que el usuario puede ver (null = todos).
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $projectIds = $user->accessibleProjectIds();

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function format()
    {
        return $this->belongsTo(FtraFormat::class, 'format_id');
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by_id');
    }

    /** Relaciones que acompañan al registro en las respuestas de la API. */
    public const DETAIL_RELATIONS = [
        'contractor', 'format', 'photos', 'tower:id,name,code',
        'registeredBy:id,name', 'responsable:id,name,email,position_id', 'responsable.position:id,name',
        'supervisorUser:id,name', 'directorUser:id,name', 'history.user:id,name',
    ];

    public function supervisorUser()
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function directorUser()
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    public function history()
    {
        return $this->hasMany(FtraRecordHistory::class, 'ftra_record_id')->orderBy('id');
    }

    /** Referencia legible: "FTRA #12 · Apto 1203" */
    public function reference(): string
    {
        return 'FTRA #' . $this->id . ($this->apartamento ? ' · Apto ' . $this->apartamento : '');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class);
    }

    /** Residente responsable de la revisión: el usuario de Elite que registró el FTRA. */
    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    /** Catálogo anterior de residentes (solo registros antiguos). */
    public function legacyResidente()
    {
        return $this->belongsTo(Residente::class, 'responsable_id');
    }

    public function photos()
    {
        return $this->hasMany(FtraRecordPhoto::class, 'ftra_record_id');
    }
}
