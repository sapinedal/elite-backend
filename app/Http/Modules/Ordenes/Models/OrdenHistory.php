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
