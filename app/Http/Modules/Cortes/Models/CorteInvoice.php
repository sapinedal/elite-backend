<?php

namespace App\Http\Modules\Cortes\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Factura (o nota crédito / factura de reemplazo) cargada a un corte aprobado.
 */
class CorteInvoice extends Model
{
    protected $table = 'cortes_invoices';

    public const TYPE_FACTURA_INICIAL = 'factura_inicial';
    public const TYPE_NOTA_CREDITO = 'nota_credito';
    public const TYPE_FACTURA_REEMPLAZO = 'factura_reemplazo';

    public const DOCUMENT_TYPES = [
        self::TYPE_FACTURA_INICIAL => 'Factura inicial',
        self::TYPE_NOTA_CREDITO => 'Nota crédito',
        self::TYPE_FACTURA_REEMPLAZO => 'Factura de reemplazo',
    ];

    protected $fillable = [
        'corte_id',
        'invoice_number',
        'document_type',
        'related_document',
        'amount',
        'multifox_confirmed_at',
        'multifox_confirmed_by',
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
        'multifox_confirmed_at' => 'datetime',
    ];

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function multifoxConfirmer()
    {
        return $this->belongsTo(User::class, 'multifox_confirmed_by');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }

    /**
     * Las notas crédito y facturas de reemplazo siempre afectan a una factura anterior.
     */
    public static function requiresRelatedDocument(?string $type): bool
    {
        return in_array($type, [self::TYPE_NOTA_CREDITO, self::TYPE_FACTURA_REEMPLAZO], true);
    }
}
