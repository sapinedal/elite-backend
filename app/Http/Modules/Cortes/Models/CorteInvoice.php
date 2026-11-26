<?php

namespace App\Http\Modules\Cortes\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Factura (o nota crédito / factura de reemplazo) cargada a un corte aprobado.
 */
class CorteInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'cortes_invoices';

    public const TYPE_FACTURA_INICIAL = 'factura_inicial';
    public const TYPE_NOTA_CREDITO = 'nota_credito';
    public const TYPE_FACTURA_REEMPLAZO = 'factura_reemplazo';

    // Aprobación de Contabilidad después de Multifox (null mientras no se carga en Multifox)
    public const ACCOUNTING_PENDIENTE = 'pendiente';
    public const ACCOUNTING_APROBADA = 'aprobada';
    public const ACCOUNTING_DEVUELTA = 'devuelta';
    // Factura anulada con una nota crédito antes de llegar a cuentas por pagar: ya no se carga en Multifox
    public const ACCOUNTING_ANULADA = 'anulada';

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
        'multifox_file_name',
        'multifox_file_path',
        'multifox_file_size',
        'multifox_file_mime',
        'accounting_status',
        'accounting_by',
        'accounting_at',
        'accounting_comment',
        'observation',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'multifox_file_size' => 'integer',
        'amount' => 'decimal:2',
        'multifox_confirmed_at' => 'datetime',
        'accounting_at' => 'datetime',
    ];

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function multifoxConfirmer()
    {
        return $this->belongsTo(User::class, 'multifox_confirmed_by');
    }

    public function accountingUser()
    {
        return $this->belongsTo(User::class, 'accounting_by');
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
    /**
     * La nota crédito no se carga en Multifox: pasa directo a la aprobación de Contabilidad. La factura anulada por
     * una nota crédito tampoco. Las demás facturas (inicial, de reemplazo) sí.
     */
    public function requiresMultifox(): bool
    {
        return $this->document_type !== self::TYPE_NOTA_CREDITO && $this->accounting_status !== self::ACCOUNTING_ANULADA;
    }

    public static function requiresRelatedDocument(?string $type): bool
    {
        return in_array($type, [self::TYPE_NOTA_CREDITO, self::TYPE_FACTURA_REEMPLAZO], true);
    }
}
