<?php

namespace App\Http\Modules\CuentasCobro\Models;

use App\Http\Modules\CuentasCobro\Service\CuentaCobroWorkflow;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Documento de una cuenta de cobro directa (con frente): cargue ERP, factura (asociada desde Facturas del mes)
 * o liquidación ERP.
 */
class CuentaCobroDocument extends Model
{
    protected $table = 'cuentas_cobro_documents';

    protected $fillable = [
        'cuenta_cobro_id',
        'step',
        'number',
        'amount',
        'observation',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stepLabel(): string
    {
        return CuentaCobroWorkflow::STEPS[$this->step]['label'] ?? $this->step;
    }
}
