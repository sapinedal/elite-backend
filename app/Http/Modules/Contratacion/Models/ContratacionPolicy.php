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
        'last_alert_days',
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
     * Umbral de aviso en el que está hoy la póliza: el menor de config('contratacion.policy_alert_thresholds')
     * que ya alcanzó (con 25 días -> 30; vencida -> 20). Null si aún no entra en ninguno.
     */
    public function alertThreshold(): ?int
    {
        $days = $this->daysToExpire();

        return collect(config('contratacion.policy_alert_thresholds'))->filter(fn (int $t) => $days <= $t)->min();
    }

    /**
     * Le toca aviso si entró en un umbral menor al último avisado (cada umbral se avisa una sola vez).
     */
    public function needsAlert(): bool
    {
        $threshold = $this->alertThreshold();

        return $threshold !== null && ($this->last_alert_days === null || $this->last_alert_days > $threshold);
    }

    /**
     * Días que faltan para el vencimiento (negativo si ya venció).
     */
    public function daysToExpire(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }
}
