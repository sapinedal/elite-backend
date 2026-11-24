<?php

namespace App\Http\Modules\Facturacion\Models;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Factura del mes cargada por Contabilidad. Queda pendiente hasta que el residente responsable la asocia
 * a su corte aprobado o a su orden de compra (queda procesada por obra).
 */
class FacturacionInvoice extends Model
{
    protected $table = 'facturacion_invoices';

    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_PROCESADA = 'procesada';

    public const TARGET_CORTE = 'corte';
    public const TARGET_ORDEN = 'orden';
    public const TARGET_ANTICIPO = 'anticipo';
    public const TARGET_PAGOS = 'pagos'; // Indirecto: pasa directo a cuentas por pagar

    public const TYPE_FACTURA = 'factura';
    public const TYPE_CUENTA_COBRO = 'cuenta_cobro';
    public const TYPE_NOTA_CREDITO = 'nota_credito';
    public const TYPE_FACTURA_REEMPLAZO = 'factura_reemplazo';
    public const TYPE_REEMBOLSO = 'reembolso';
    public const TYPE_COTIZACION = 'cotizacion';
    public const TYPE_INDIRECTO = 'indirecto';

    /**
     * Tipos con su etiqueta, el prefijo con el que se nombran en la carpeta (FE 335, C-C 49) y el tipo
     * equivalente en las facturas del corte. La cotización no va a cortes: se radica como anticipo (corte_type null).
     * El indirecto no se asocia a nada en obra: al cargarlo pasa directo a cuentas por pagar.
     */
    public const DOCUMENT_TYPES = [
        self::TYPE_FACTURA => ['label' => 'Factura electrónica', 'prefix' => 'FE', 'corte_type' => CorteInvoice::TYPE_FACTURA_INICIAL],
        self::TYPE_CUENTA_COBRO => ['label' => 'Cuenta de cobro', 'prefix' => 'C-C', 'corte_type' => CorteInvoice::TYPE_FACTURA_INICIAL],
        self::TYPE_NOTA_CREDITO => ['label' => 'Nota crédito', 'prefix' => 'NC', 'corte_type' => CorteInvoice::TYPE_NOTA_CREDITO],
        self::TYPE_FACTURA_REEMPLAZO => ['label' => 'Factura de reemplazo', 'prefix' => 'FE', 'corte_type' => CorteInvoice::TYPE_FACTURA_REEMPLAZO],
        self::TYPE_REEMBOLSO => ['label' => 'Reembolso', 'prefix' => 'RE', 'corte_type' => CorteInvoice::TYPE_FACTURA_INICIAL],
        self::TYPE_COTIZACION => ['label' => 'Cotización', 'prefix' => 'COT', 'corte_type' => null],
        self::TYPE_INDIRECTO => ['label' => 'Indirecto', 'prefix' => 'IND', 'corte_type' => null],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE => 'Pendiente de obra',
        self::STATUS_PROCESADA => 'Procesada por obra',
    ];

    protected $fillable = [
        'period',
        'contractor_id',
        'supplier_name',
        'document_type',
        'invoice_number',
        'related_document',
        'amount',
        'observation',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'uploaded_by',
        'status',
        'target_type',
        'corte_id',
        'orden_id',
        'corte_invoice_id',
        'orden_document_id',
        'anticipo_id',
        'pago_cuenta_id',
        'processed_comment',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'period' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function corte()
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function anticipo()
    {
        return $this->belongsTo(Anticipo::class, 'anticipo_id');
    }

    public function pagoCuenta()
    {
        return $this->belongsTo(PagoCuenta::class, 'pago_cuenta_id');
    }

    public function supports()
    {
        return $this->hasMany(FacturacionInvoiceSupport::class, 'invoice_id');
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type]['label'] ?? $this->document_type;
    }

    /**
     * Número como se nombra en contabilidad y en cuentas por pagar: "FE 335", "C-C 15102026".
     */
    public function documentNumber(): string
    {
        $prefix = self::DOCUMENT_TYPES[$this->document_type]['prefix'] ?? '';

        return trim("{$prefix} {$this->invoice_number}");
    }

    public static function requiresRelatedDocument(?string $type): bool
    {
        return in_array($type, [self::TYPE_NOTA_CREDITO, self::TYPE_FACTURA_REEMPLAZO], true);
    }

    /**
     * La cotización entra como anticipo de un contrato, no a un corte ni a una orden.
     */
    public static function isAnticipo(?string $type): bool
    {
        return $type === self::TYPE_COTIZACION;
    }

    /**
     * El indirecto no pide asociarse a corte, orden ni entrada: al cargarlo pasa a cuentas por pagar.
     */
    public static function isIndirecto(?string $type): bool
    {
        return $type === self::TYPE_INDIRECTO;
    }

    /**
     * Documentos del proveedor que se asocian a una orden de compra (pendiente de factura).
     */
    public static function fitsOrden(?string $type): bool
    {
        return in_array($type, [self::TYPE_FACTURA, self::TYPE_CUENTA_COBRO, self::TYPE_REEMBOLSO], true);
    }
}
