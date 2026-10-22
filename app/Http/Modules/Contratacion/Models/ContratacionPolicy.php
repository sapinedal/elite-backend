<?php

namespace App\Http\Modules\Contratacion\Models;

use Illuminate\Database\Eloquent\Model;

class ContratacionPolicy extends Model
{
    protected $table = 'contratacion_policies';

    public const POLICY_TYPES = [
        'Cumplimiento',
        'Anticipo',
        'Responsabilidad civil extracontractual',
        'Salarios y prestaciones sociales',
        'Calidad y correcto funcionamiento',
        'Estabilidad de la obra',
        'Otra',
    ];

    protected $fillable = [
        'contract_id',
        'policy_type',
        'policy_number',
        'insurer',
        'expires_at',
        'alert_sent_at',
    ];

    protected $casts = [
        'expires_at' => 'date:Y-m-d',
        'alert_sent_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

    /**
     * Días que faltan para el vencimiento (negativo si ya venció).
     */
    public function daysToExpire(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }
}
