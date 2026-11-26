<?php

namespace App\Http\Modules\Cortes\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Trazabilidad de un corte: cada radicación, aprobación, devolución y reenvío.
 */
class CorteHistory extends Model
{
    protected $table = 'cortes_history';

    // Solo se registra la fecha de creación: el historial no se edita
    public const UPDATED_AT = null;

    public const ACTION_RADICADO = 'radicado';
    public const ACTION_APROBADO = 'aprobado';
    public const ACTION_DEVUELTO = 'devuelto';
    public const ACTION_RECHAZADO_CONTINUA = 'rechazado_continua'; // Interventor rechaza pero el corte sigue (Rechazado pero aprobado)
    public const ACTION_REENVIADO = 'reenviado';
    public const ACTION_FACTURA = 'factura';
    public const ACTION_MULTIFOX = 'multifox'; // Factura confirmada en Multifox: pasa a la aprobación de Contabilidad
    public const ACTION_CONTABILIDAD_APROBADA = 'contabilidad_aprobada'; // Contabilidad aprueba: pasa a cuentas por pagar
    public const ACTION_CONTABILIDAD_DEVUELTA = 'contabilidad_devuelta'; // Contabilidad devuelve a quien carga en Multifox
    public const ACTION_FACTURA_ELIMINADA = 'factura_eliminada'; // Factura eliminada por error (se reversa en cuentas por pagar)
    public const ACTION_ELIMINADO = 'eliminado'; // Corte eliminado por error

    protected $fillable = [
        'corte_id',
        'stage',
        'action',
        'comment',
        'file_name',
        'file_path',
        'user_id',
    ];

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
