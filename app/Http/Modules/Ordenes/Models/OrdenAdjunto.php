<?php

namespace App\Http\Modules\Ordenes\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Adjunto opcional de la orden, cargado al registrarla o reenviarla (cotizaciones, fichas técnicas, fotos...).
 */
class OrdenAdjunto extends Model
{
    protected $table = 'ordenes_adjuntos';

    protected $fillable = [
        'orden_id',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
