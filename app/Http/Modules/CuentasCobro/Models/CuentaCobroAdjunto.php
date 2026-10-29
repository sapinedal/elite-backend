<?php

namespace App\Http\Modules\CuentasCobro\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class CuentaCobroAdjunto extends Model
{
    protected $table = 'cuentas_cobro_adjuntos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'cuenta_cobro_id',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaCobro::class, 'cuenta_cobro_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
