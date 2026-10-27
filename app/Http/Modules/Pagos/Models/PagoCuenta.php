<?php

namespace App\Http\Modules\Pagos\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cuenta por pagar: una factura (o cuenta de cobro, nómina, aporte...) con su valor, ajustes y pagos.
 */
class PagoCuenta extends Model
{
    use SoftDeletes;

    protected $table = 'pagos_cuentas';

    public const SOURCE_CORTE = 'corte';
    public const SOURCE_ORDEN = 'orden';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'source_type',
        'source_id',
        'corte_id',
        'orden_id',
        'project_id',
        'frente',
        'contractor_id',
        'supplier_nit',
        'supplier_name',
        'document_number',
        'concept',
        'amount',
        'adjustments',
        'paid_amount',
        'status',
        'priority',
        'scheduled_for',
        'prioritized_by',
        'prioritized_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'scheduled_for' => 'date',
        'prioritized_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Saldo pendiente: valor - notas crédito - pagos.
     */
    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->adjustments - (float) $this->paid_amount, 2);
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function prioritizer()
    {
        return $this->belongsTo(User::class, 'prioritized_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movimientos()
    {
        return $this->hasMany(PagoMovimiento::class, 'cuenta_id')->orderBy('id');
    }
}
