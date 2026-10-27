<?php

namespace App\Http\Modules\Pagos\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Trazabilidad de una cuenta por pagar: creación, priorización, pagos, notas crédito, reemplazos y anulaciones.
 */
class PagoMovimiento extends Model
{
    protected $table = 'pagos_movimientos';

    // El historial no se edita
    public const UPDATED_AT = null;

    public const ACTION_CREADA = 'creada';
    public const ACTION_PRIORIZADA = 'priorizada';
    public const ACTION_DESPROGRAMADA = 'desprogramada';
    public const ACTION_PAGO = 'pago';
    public const ACTION_NOTA_CREDITO = 'nota_credito';
    public const ACTION_REEMPLAZO = 'reemplazo';
    public const ACTION_ANULADA = 'anulada';

    protected $fillable = [
        'cuenta_id',
        'action',
        'amount',
        'payment_date',
        'reference',
        'comment',
        'file_name',
        'file_path',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function cuenta()
    {
        return $this->belongsTo(PagoCuenta::class, 'cuenta_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
