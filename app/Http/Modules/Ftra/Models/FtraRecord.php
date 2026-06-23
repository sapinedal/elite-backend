<?php

namespace App\Http\Modules\Ftra\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class FtraRecord extends Model
{
    protected $table = 'ftra_records';

    protected $fillable = [
        'contractor_id',
        'format_id',
        'observations',
        'is_completed',
        'status',
        'registered_by_id',
        'contractor_signature',
        'resident_signature',
        'responsable_id',
        'resultado_inspeccion',
        'orden_aseo',
        'piso',
        'apartamento',
        'director_signature',
        'supervisor_signature',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

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

    public function responsable()
    {
        return $this->belongsTo(Residente::class, 'responsable_id');
    }

    public function photos()
    {
        return $this->hasMany(FtraRecordPhoto::class, 'ftra_record_id');
    }
}
