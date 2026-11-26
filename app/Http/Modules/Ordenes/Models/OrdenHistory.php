<?php

namespace App\Http\Modules\Ordenes\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Trazabilidad de una orden: registro, aprobaciones, devoluciones, reenvíos y cargue de documentos.
 */
class OrdenHistory extends Model
{
    protected $table = 'ordenes_history';

    // Solo se registra la fecha de creación: el historial no se edita
    public const UPDATED_AT = null;

    public const ACTION_REGISTRADA = 'registrada';
    public const ACTION_APROBADO = 'aprobado';
    public const ACTION_DEVUELTO = 'devuelto';
    public const ACTION_REENVIADA = 'reenviada';
    public const ACTION_DOCUMENTO = 'documento';
    public const ACTION_CONTABILIDAD_APROBADA = 'contabilidad_aprobada'; // Contabilidad aprueba: pasa a cuentas por pagar
    public const ACTION_PAGO_ANTICIPADO = 'pago_anticipado'; // Orden ERP enviada a cuentas por pagar con su cargue (la entrada se legaliza después)
    public const ACTION_CONTABILIDAD_DEVUELTA = 'contabilidad_devuelta'; // Contabilidad devuelve a Almacén (entrada)
    public const ACTION_DOCUMENTO_ELIMINADO = 'documento_eliminado'; // Documento (orden ERP, factura o entrada) eliminado por error
    public const ACTION_ELIMINADA = 'eliminada'; // Orden eliminada por error

    protected $fillable = [
        'orden_id',
        'stage',
        'action',
        'comment',
        'file_name',
        'user_id',
    ];

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
