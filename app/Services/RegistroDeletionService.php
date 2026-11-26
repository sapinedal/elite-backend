<?php

namespace App\Services;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Models\AnticipoHistory;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroHistory;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Service\FacturacionStorageService;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Models\OrdenHistory;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Eliminación de registros cargados por error, en cualquier estado (aprobados, facturados, completados).
 *
 * Cada eliminación pide un motivo, queda en el historial del registro y en el log, y reversa lo que el registro generó:
 *  - Cuentas por pagar: se anulan (si alguna ya tiene pagos no se elimina nada: primero se revierte el pago).
 *  - Facturas del mes asociadas: vuelven a "Pendiente de obra" para asociarlas de nuevo o eliminarlas.
 *
 * Los cortes, órdenes, anticipos y cuentas de cobro se eliminan con soft delete (se pueden recuperar en base de datos);
 * las facturas de un corte y los documentos de una orden se borran para poder cargarlos de nuevo con el mismo número.
 * Los archivos se conservan en S3 (el historial los referencia), salvo el de la factura del mes eliminada.
 *
 * Permisos: cortes.eliminar, ordenes.eliminar, anticipos.eliminar, cuentas_cobro.eliminar, facturacion.eliminar.
 */
class RegistroDeletionService
{
    public const PERMISSION_CORTES = 'cortes.eliminar';
    public const PERMISSION_ORDENES = 'ordenes.eliminar';
    public const PERMISSION_ANTICIPOS = 'anticipos.eliminar';
    public const PERMISSION_CUENTAS_COBRO = 'cuentas_cobro.eliminar';
    public const PERMISSION_FACTURACION = 'facturacion.eliminar';

    public function __construct(
        protected PagoService $pagos,
        protected FacturacionStorageService $facturacionStorage,
    ) {
    }

    // ---------------------------------------------------------------- Cortes

    public function deleteCorte(Corte $corte, string $reason, User $user): void
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_CORTES, $corte->project_id, $reason);

        DB::transaction(function () use ($corte, $reason, $user) {
            $corte = Corte::whereKey($corte->id)->lockForUpdate()->firstOrFail();

            $this->pagos->annulForDeletedSource('corte_id', $corte->id, $reason, $user);
            FacturacionInvoice::releaseWhere('corte_id', $corte->id, $reason);

            $corte->history()->create([
                'action' => CorteHistory::ACTION_ELIMINADO,
                'comment' => "Corte eliminado ({$this->statusLabel(CorteWorkflow::STATUS_LABELS, $corte->status)}). Motivo: {$reason}",
                'user_id' => $user->id,
            ]);
            // Las facturas se borran para poder volver a cargar el mismo número en otro corte
            CorteInvoice::where('corte_id', $corte->id)->delete();
            $corte->delete();
        });

        $this->audit('corte', $corte->id, $corte->reference(), $reason, $user);
    }

    /**
     * Elimina una factura (o nota crédito / factura de reemplazo) de un corte. Si otra factura del corte la afecta,
     * primero hay que eliminar esa. El corte vuelve a Aprobado si se queda sin facturas.
     */
    public function deleteCorteInvoice(CorteInvoice $invoice, string $reason, User $user): Corte
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_CORTES, $invoice->corte->project_id, $reason);

        $corte = DB::transaction(function () use ($invoice, $reason, $user) {
            $corte = Corte::whereKey($invoice->corte_id)->lockForUpdate()->firstOrFail();
            $invoice = CorteInvoice::whereKey($invoice->id)->firstOrFail();

            $dependent = CorteInvoice::where('corte_id', $corte->id)->where('id', '!=', $invoice->id)
                ->where('related_document', $invoice->invoice_number)->first();
            if ($dependent) {
                throw new InvalidArgumentException("La {$dependent->documentTypeLabel()} N.º {$dependent->invoice_number} afecta esta factura: elimínala primero.");
            }

            $this->pagos->reverseCorteInvoice($invoice, $reason, $user);
            FacturacionInvoice::releaseWhere('corte_invoice_id', $invoice->id, $reason);

            $corte->history()->create([
                'action' => CorteHistory::ACTION_FACTURA_ELIMINADA,
                'comment' => "{$invoice->documentTypeLabel()} N.º {$invoice->invoice_number} eliminada. Motivo: {$reason}",
                'file_name' => $invoice->file_name,
                'file_path' => $invoice->file_path,
                'user_id' => $user->id,
            ]);
            $invoice->delete();

            if ($corte->status === CorteWorkflow::STATUS_FACTURADO && !CorteInvoice::where('corte_id', $corte->id)->exists()) {
                $corte->update(['status' => CorteWorkflow::STATUS_APROBADO]);
            }

            return $corte;
        });

        $this->audit('corte_invoice', $invoice->id, "{$invoice->invoice_number} · {$corte->reference()}", $reason, $user);

        return $corte;
    }

    // ---------------------------------------------------------------- Órdenes

    public function deleteOrden(Orden $orden, string $reason, User $user): void
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_ORDENES, $orden->project_id, $reason);

        DB::transaction(function () use ($orden, $reason, $user) {
            $orden = Orden::whereKey($orden->id)->lockForUpdate()->firstOrFail();

            $this->pagos->annulForDeletedSource('orden_id', $orden->id, $reason, $user);
            FacturacionInvoice::releaseWhere('orden_id', $orden->id, $reason);

            $orden->history()->create([
                'action' => OrdenHistory::ACTION_ELIMINADA,
                'comment' => "Orden eliminada ({$this->statusLabel(OrdenWorkflow::STATUS_LABELS, $orden->status)}). Motivo: {$reason}",
                'user_id' => $user->id,
            ]);
            $orden->delete();
        });

        $this->audit('orden', $orden->id, (string) $orden->code, $reason, $user);
    }

    /**
     * Elimina el último documento cargado de la orden (entrada, factura u orden ERP) y la orden vuelve a ese paso.
     * Los documentos son secuenciales: para borrar uno anterior se eliminan primero los siguientes.
     * Si la orden ya estaba completada, su cuenta por pagar se anula (si se pagó desde la orden ERP, solo al borrar ese
     * documento).
     */
    public function deleteOrdenDocument(OrdenDocument $document, string $reason, User $user): Orden
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_ORDENES, $document->orden->project_id, $reason);

        $orden = DB::transaction(function () use ($document, $reason, $user) {
            $orden = Orden::whereKey($document->orden_id)->lockForUpdate()->firstOrFail();
            $document = OrdenDocument::whereKey($document->id)->firstOrFail();

            $steps = array_keys(OrdenWorkflow::STEPS);
            $last = $orden->documents()->get()->sortBy(fn (OrdenDocument $d) => array_search($d->step, $steps, true))->last();
            if ($last && $last->id !== $document->id) {
                throw new InvalidArgumentException("Primero elimina el documento posterior: {$last->stepLabel()}.");
            }

            // Si ya pasó a cuentas por pagar se anula la cuenta. Si se pagó desde la orden ERP, la cuenta depende de ese
            // documento: borrar la factura o la entrada (legalización) no la anula
            $paidFromErp = $orden->paid_in_advance;
            if (!$paidFromErp || $document->step === OrdenWorkflow::STEP_ORDEN_ERP) {
                $this->pagos->annulForDeletedSource('orden_id', $orden->id, $reason, $user);
            }
            FacturacionInvoice::releaseWhere('orden_document_id', $document->id, $reason);

            $orden->history()->create([
                'stage' => $document->step,
                'action' => OrdenHistory::ACTION_DOCUMENTO_ELIMINADO,
                'comment' => "{$document->stepLabel()} N.º {$document->number} eliminada. Motivo: {$reason}",
                'file_name' => $document->file_name,
                'user_id' => $user->id,
            ]);
            $document->delete();

            $orden->update([
                'status' => OrdenWorkflow::STEPS[$document->step]['status'],
                'current_step' => $document->step,
                'completed_at' => null,
                ...($document->step === OrdenWorkflow::STEP_ORDEN_ERP ? ['paid_in_advance' => false] : []),
            ]);

            return $orden;
        });

        $this->audit('orden_document', $document->id, "{$document->stepLabel()} {$document->number} · {$orden->code}", $reason, $user);

        return $orden;
    }

    // ---------------------------------------------------------------- Anticipos y cuentas de cobro

    public function deleteAnticipo(Anticipo $anticipo, string $reason, User $user): void
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_ANTICIPOS, $anticipo->project_id, $reason);

        DB::transaction(function () use ($anticipo, $reason, $user) {
            $anticipo = Anticipo::whereKey($anticipo->id)->lockForUpdate()->firstOrFail();

            $this->pagos->annulForDeletedSource('anticipo_id', $anticipo->id, $reason, $user);
            FacturacionInvoice::releaseWhere('anticipo_id', $anticipo->id, $reason);

            $anticipo->history()->create([
                'action' => AnticipoHistory::ACTION_ELIMINADO,
                'comment' => "Anticipo eliminado. Motivo: {$reason}",
                'user_id' => $user->id,
            ]);
            $anticipo->delete();
        });

        $this->audit('anticipo', $anticipo->id, (string) $anticipo->code, $reason, $user);
    }

    public function deleteCuentaCobro(CuentaCobro $cuenta, string $reason, User $user): void
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_CUENTAS_COBRO, $cuenta->project_id, $reason);

        DB::transaction(function () use ($cuenta, $reason, $user) {
            $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();

            $this->pagos->annulForDeletedSource('cuenta_cobro_id', $cuenta->id, $reason, $user);

            $cuenta->history()->create([
                'action' => CuentaCobroHistory::ACTION_ELIMINADA,
                'comment' => "Cuenta de cobro eliminada. Motivo: {$reason}",
                'user_id' => $user->id,
            ]);
            $cuenta->delete();
        });

        $this->audit('cuenta_cobro', $cuenta->id, (string) $cuenta->code, $reason, $user);
    }

    // ---------------------------------------------------------------- Facturas del mes

    /**
     * Elimina una factura del mes ya procesada por obra, con todo lo que generó: la factura del corte, el documento
     * de la orden (la orden vuelve a pendiente de factura), el anticipo o la cuenta por pagar del indirecto.
     * Las pendientes las elimina Contabilidad con cortes.facturar (FacturacionService::delete).
     */
    public function deleteFacturaProcesada(FacturacionInvoice $invoice, string $reason, User $user): void
    {
        $reason = $this->assertAllowed($user, self::PERMISSION_FACTURACION, null, $reason);

        // Primero se reversa el registro de obra (cada uno en su transacción); si falla, la factura queda intacta
        match ($invoice->target_type) {
            FacturacionInvoice::TARGET_CORTE => $invoice->corte_invoice_id && ($ci = CorteInvoice::find($invoice->corte_invoice_id))
                ? $this->deleteCorteInvoice($ci, $reason, $user) : null,
            FacturacionInvoice::TARGET_ORDEN => $invoice->orden_document_id && ($doc = OrdenDocument::find($invoice->orden_document_id))
                ? $this->deleteOrdenDocument($doc, $reason, $user) : null,
            FacturacionInvoice::TARGET_ANTICIPO => $invoice->anticipo_id && ($anticipo = Anticipo::find($invoice->anticipo_id))
                ? $this->deleteAnticipo($anticipo, $reason, $user) : null,
            default => null,
        };

        DB::transaction(function () use ($invoice, $reason, $user) {
            $invoice = FacturacionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->target_type === FacturacionInvoice::TARGET_PAGOS && $invoice->pago_cuenta_id) {
                $this->pagos->annulForDeletedSource('id', $invoice->pago_cuenta_id, $reason, $user);
            }
            $invoice->supports()->delete();
            $invoice->delete();
        });

        $this->facturacionStorage->delete($invoice->file_path);
        $this->audit('facturacion_invoice', $invoice->id, $invoice->documentNumber(), $reason, $user);
    }

    // ---------------------------------------------------------------- Apoyo

    public static function canDelete(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    protected function assertAllowed(User $user, string $permission, ?int $projectId, ?string $reason): string
    {
        if (!$user->can($permission)) {
            throw new InvalidArgumentException('No tienes permiso para eliminar este registro.');
        }
        if ($projectId !== null && !$user->canAccessProject($projectId)) {
            throw new InvalidArgumentException('No tienes acceso al proyecto de este registro.');
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Indica el motivo de la eliminación (mínimo 5 caracteres).');
        }

        return $reason;
    }

    protected function statusLabel(array $labels, ?string $status): string
    {
        return $labels[$status] ?? (string) $status;
    }

    protected function audit(string $type, int $id, string $reference, string $reason, User $user): void
    {
        Log::warning('Registro eliminado', [
            'type' => $type,
            'id' => $id,
            'reference' => $reference,
            'reason' => $reason,
            'user_id' => $user->id,
            'user' => $user->name,
        ]);
    }
}
