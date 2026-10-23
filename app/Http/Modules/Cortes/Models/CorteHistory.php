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
    public const ACTION_REENVIADO = 'reenviado';

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
