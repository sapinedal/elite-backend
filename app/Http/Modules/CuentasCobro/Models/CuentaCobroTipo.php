<?php

namespace App\Http\Modules\CuentasCobro\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cuenta de cobro (SST, Gestión ambiental...). Define si la cuenta exige adjuntos.
 */
class CuentaCobroTipo extends Model
{
    protected $table = 'cuentas_cobro_tipos';

    protected $fillable = [
        'name',
        'description',
        'requires_attachment',
        'attachment_hint',
        'is_active',
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cuentas()
    {
        return $this->hasMany(CuentaCobro::class, 'tipo_id');
    }
}
