<?php

namespace App\Http\Modules\Ordenes\Models;

use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Documento de un paso posterior a la aprobación: orden de compra del ERP, factura del proveedor o entrada de almacén.
 */
class OrdenDocument extends Model
{
    protected $table = 'ordenes_documents';

    protected $fillable = [
        'orden_id',
        'step',
        'number',
        'amount',
        'contractor_id',
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

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function contractor()
    {
        return $this->belongsTo(\App\Http\Modules\Ftra\Models\FtraContractor::class, 'contractor_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stepLabel(): string
    {
        return OrdenWorkflow::STEPS[$this->step]['label'] ?? $this->step;
    }
}
