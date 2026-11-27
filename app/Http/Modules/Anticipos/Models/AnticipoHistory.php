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
    public const ACTION_SOPORTE = 'soporte'; // El residente adjuntó el contrato / orden de compra: pasa a Contabilidad (Multifox)
    public const ACTION_MULTIFOX = 'multifox'; // Contabilidad generó el anticipo en Multifox: pasa a la aprobación de Contabilidad
    public const ACTION_CONTABILIDAD_APROBADA = 'contabilidad_aprobada'; // Aprobado por Contabilidad: pasa al cuadro de pagos
    public const ACTION_CONTABILIDAD_DEVUELTA = 'contabilidad_devuelta'; // Devuelto a quien lo genera en Multifox
    public const ACTION_ELIMINADO = 'eliminado'; // Anticipo eliminado por error
    public const ACTION_LEGALIZADO = 'legalizado'; // Se asoció una factura de Facturas del mes que legaliza el anticipo

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
