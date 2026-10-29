<?php

namespace App\Http\Modules\Anticipos\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnticipoHistory extends Model
{
    protected $table = 'anticipos_history';

    public const UPDATED_AT = null;

    public const ACTION_RADICADO = 'radicado';
    public const ACTION_APROBADO = 'aprobado';
    public const ACTION_DEVUELTO = 'devuelto';
    public const ACTION_REENVIADO = 'reenviado';
    public const ACTION_MULTIFOX = 'multifox'; // Contabilidad generó el anticipo en Multifox: pasa al cuadro de pagos

    protected $fillable = [
        'anticipo_id',
        'stage',
        'action',
        'comment',
        'file_name',
        'file_path',
        'user_id',
    ];

    public function anticipo()
    {
        return $this->belongsTo(Anticipo::class, 'anticipo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
