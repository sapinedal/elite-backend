<?php

namespace App\Http\Modules\Facturacion\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soporte que adjunta el residente al asociar la factura (capturas de aprobaciones, corte o entrada de Multifox).
 */
class FacturacionInvoiceSupport extends Model
{
    use SoftDeletes;

    protected $table = 'facturacion_invoice_supports';

    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(FacturacionInvoice::class, 'invoice_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
