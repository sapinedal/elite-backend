<?php

namespace App\Http\Modules\CuentasCobro\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class CuentaCobroHistory extends Model
{
    protected $table = 'cuentas_cobro_history';

    public const UPDATED_AT = null;

    public const ACTION_RADICADA = 'radicada';
    public const ACTION_APROBADO = 'aprobado';
    public const ACTION_DEVUELTO = 'devuelto';
    public const ACTION_REENVIADA = 'reenviada';
    public const ACTION_SIN_MULTIFOX = 'sin_multifox';   // Paula decidió que no requiere Multifox: pasa al cuadro de pagos
    public const ACTION_REQUIERE_MULTIFOX = 'requiere_multifox'; // Paula decidió que requiere Multifox
    public const ACTION_MULTIFOX = 'multifox';           // Cargada en Multifox: pasa al cuadro de pagos

    protected $fillable = [
        'cuenta_cobro_id',
        'stage',
        'action',
        'comment',
        'file_name',
        'user_id',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
