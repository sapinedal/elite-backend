<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Facturas de los cortes aprobados: registro en el corte, cambio de estado a Facturado,
 * historial y aviso al cargo que la registra en Multifox.
 *
 * Las facturas llegan desde las facturas del mes (módulo Facturación): Contabilidad las carga en la
 * carpeta del mes y el residente las asocia a su corte, así que el archivo ya está en S3.
 */
class CorteInvoiceService
{
    public function __construct(
        protected CorteNotificationService $notifications,
        protected PagoService $pagos,
    ) {
    }

    /**
     * Registra en el corte una factura cuyo archivo ya está guardado ($file). Debe llamarse dentro de una
     * transacción; el aviso de Multifox lo envía notifyUploaded() después del commit.
     *
     * @param array{file_name: string, file_path: string, file_size: int, file_mime: ?string} $file
     */
    public function register(Corte $corte, array $data, array $file, User $user): CorteInvoice
    {
        $affectsAnother = CorteInvoice::requiresRelatedDocument($data['document_type']);
        $number = trim($data['invoice_number']);

        $corte = Corte::whereKey($corte->id)->lockForUpdate()->firstOrFail();
        if (!in_array($corte->status, CorteWorkflow::INVOICEABLE_STATUSES, true)) {
            throw new InvalidArgumentException('Solo se pueden asociar facturas a cortes con las tres aprobaciones.');
        }
        // El mismo número no se repite dentro del mismo tipo de documento
        if (CorteInvoice::where('document_type', $data['document_type'])->where('invoice_number', $number)->exists()) {
            throw new InvalidArgumentException("Ya existe un documento {$number} registrado en otro corte.");
        }

        $invoice = $corte->invoices()->create([
            'invoice_number' => $number,
            'document_type' => $data['document_type'],
            // En la factura inicial no aplica documento relacionado aunque llegue
            'related_document' => $affectsAnother ? trim($data['related_document']) : null,
            'amount' => $data['amount'],
            'observation' => isset($data['observation']) ? trim($data['observation']) : null,
            'uploaded_by' => $user->id,
            ...$file,
        ]);

        if ($corte->status === CorteWorkflow::STATUS_APROBADO) {
            $corte->update(['status' => CorteWorkflow::STATUS_FACTURADO]);
        }

        $summary = $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number} por $" . number_format((float) $invoice->amount, 0, ',', '.')
            . ($invoice->related_document ? " (afecta a {$invoice->related_document})" : '')
            . ($invoice->observation ? ". {$invoice->observation}" : '');

        $corte->history()->create([
            'action' => CorteHistory::ACTION_FACTURA,
            'comment' => $summary,
            'file_name' => $invoice->file_name,
            'file_path' => $invoice->file_path,
            'user_id' => $user->id,
        ]);

        return $invoice;
    }

    /**
     * Aviso al cargo que registra la factura en Multifox. Después del commit: no avisar de una factura que no quedó guardada.
     */
    public function notifyUploaded(CorteInvoice $invoice): void
    {
        $this->notifications->invoiceUploaded($invoice->fresh(['corte', 'uploader']));
    }

    /**
     * Paula confirma que cargó la factura en Multifox: queda en el historial y pasa a cuentas por pagar
     * (la nota crédito descuenta de la factura que afecta y la de reemplazo sustituye a la anterior).
     */
    public function confirmMultifox(CorteInvoice $invoice, User $user): CorteInvoice
    {
        if (!CorteWorkflow::canConfirmMultifox($user) || !$user->canAccessProject($invoice->corte->project_id)) {
            throw new InvalidArgumentException('Solo quien registra las facturas en Multifox puede confirmarlas.');
        }

        return DB::transaction(function () use ($invoice, $user) {
            $invoice = CorteInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->multifox_confirmed_at) {
                throw new InvalidArgumentException('Esta factura ya fue confirmada en Multifox.');
            }
            if ($invoice->amount === null) {
                throw new InvalidArgumentException('La factura no tiene valor registrado; no se puede pasar a cuentas por pagar.');
            }

            $invoice->update(['multifox_confirmed_at' => now(), 'multifox_confirmed_by' => $user->id]);
            $this->pagos->registerFromCorteInvoice($invoice, $user);

            $invoice->corte->history()->create([
                'action' => CorteHistory::ACTION_MULTIFOX,
                'comment' => $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number} cargada en Multifox. Pasa a cuentas por pagar.",
                'file_name' => $invoice->file_name,
                'file_path' => $invoice->file_path,
                'user_id' => $user->id,
            ]);

            return $invoice;
        });
    }
}
