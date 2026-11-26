<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Pagos\Service\AccountingApprovalNotifier;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Users\Service\MailRecipients;
use App\Services\DirectUploadService;
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
        protected CorteStorageService $storage,
        protected DirectUploadService $uploads,
        protected AccountingApprovalNotifier $accounting,
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
            'accounting_status' => null,
            'observation' => isset($data['observation']) ? trim($data['observation']) : null,
            'uploaded_by' => $user->id,
            ...$file,
        ]);

        if ($corte->status === CorteWorkflow::STATUS_APROBADO) {
            $corte->update(['status' => CorteWorkflow::STATUS_FACTURADO]);
        }

        // La factura que anula la nota crédito, si aún no llegó a cuentas por pagar, ya no se carga en Multifox
        $voided = $invoice->document_type === CorteInvoice::TYPE_NOTA_CREDITO
            ? $corte->invoices()->where('id', '!=', $invoice->id)
                ->where('document_type', '!=', CorteInvoice::TYPE_NOTA_CREDITO)
                ->whereRaw('UPPER(TRIM(invoice_number)) = ?', [mb_strtoupper(trim((string) $invoice->related_document))])
                ->where(fn ($q) => $q->whereNull('accounting_status')->orWhere('accounting_status', '!=', CorteInvoice::ACCOUNTING_APROBADA))
                ->get()
            : collect();
        foreach ($voided as $anulada) {
            $anulada->update(['accounting_status' => CorteInvoice::ACCOUNTING_ANULADA]);
        }

        // La nota crédito se aplica de una vez (sin Multifox ni aprobación de Contabilidad): descuenta de la cuenta
        // por pagar de la factura que afecta, si ya estaba allí. Queda registrado el valor aplicado.
        $applied = null;
        if ($invoice->document_type === CorteInvoice::TYPE_NOTA_CREDITO) {
            $applied = $this->pagos->applyCorteCreditNote($invoice, $user);
            $invoice->update([
                'accounting_status' => CorteInvoice::ACCOUNTING_APROBADA,
                'accounting_by' => $user->id,
                'accounting_at' => now(),
                ...($invoice->amount === null && $applied !== null ? ['amount' => $applied] : []),
            ]);
        }

        $summary = $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number}"
            . ($invoice->amount !== null ? ' por $' . number_format((float) $invoice->amount, 0, ',', '.') : '')
            . ($invoice->related_document ? " (afecta a {$invoice->related_document})" : '')
            . ($invoice->observation ? ". {$invoice->observation}" : '')
            . ($invoice->document_type === CorteInvoice::TYPE_NOTA_CREDITO
                ? ($applied !== null
                    ? '. Nota crédito aplicada: descuenta $' . number_format($applied, 0, ',', '.') . " de la cuenta por pagar de {$invoice->related_document}"
                    : '. Nota crédito aplicada (la factura que afecta no estaba en cuentas por pagar)')
                : '')
            . ($voided->isNotEmpty() ? '. Anula ' . $voided->pluck('invoice_number')->implode(', ') . ', que ya no se carga en Multifox' : '');

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
        $invoice = $invoice->fresh(['corte', 'uploader']);

        // Las facturas van a quien las carga en Multifox; la nota crédito ya quedó aplicada y no pide nada a nadie
        if ($invoice->requiresMultifox()) {
            $this->notifications->invoiceUploaded($invoice);
        }
    }

    /**
     * Paula confirma que cargó la factura en Multifox y adjunta el soporte, que queda en la carpeta del corte
     * (<contrato>/05 CORTES/CORTE N/). Queda en el historial y pasa a la aprobación de Contabilidad, que la
     * envía a cuentas por pagar (ver reviewAccounting).
     */
    public function confirmMultifox(CorteInvoice $invoice, string $uploadToken, User $user): CorteInvoice
    {
        if (!CorteWorkflow::canConfirmMultifox($user) || !$user->canAccessProject($invoice->corte->project_id)) {
            throw new InvalidArgumentException('Solo quien registra las facturas en Multifox puede confirmarlas.');
        }
        if ($invoice->multifox_confirmed_at) {
            throw new InvalidArgumentException('Esta factura ya fue confirmada en Multifox.');
        }
        if (!$invoice->requiresMultifox()) {
            throw new InvalidArgumentException('Las notas crédito y las facturas anuladas con nota crédito no se cargan en Multifox.');
        }
        // El soporte se mueve a la carpeta del corte antes de la transacción (igual que los demás cargues)
        $file = $this->storage->claimCorteFile($uploadToken, $user, $this->storage->folderOf($invoice->corte));

        try {
            $invoice = DB::transaction(function () use ($invoice, $file, $user) {
                $invoice = CorteInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                if ($invoice->multifox_confirmed_at) {
                    throw new InvalidArgumentException('Esta factura ya fue confirmada en Multifox.');
                }

                $invoice->update([
                    'multifox_confirmed_at' => now(),
                    'multifox_confirmed_by' => $user->id,
                    'multifox_file_name' => $file['file_name'],
                    'multifox_file_path' => $file['file_path'],
                    'multifox_file_size' => $file['file_size'],
                    'multifox_file_mime' => $file['file_mime'],
                    'accounting_status' => CorteInvoice::ACCOUNTING_PENDIENTE,
                    'accounting_by' => null,
                    'accounting_at' => null,
                ]);

                $invoice->corte->history()->create([
                    'action' => CorteHistory::ACTION_MULTIFOX,
                    'comment' => $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number} cargada en Multifox. Pendiente de aprobación de Contabilidad.",
                    'file_name' => $file['file_name'],
                    'file_path' => $file['file_path'],
                    'user_id' => $user->id,
                ]);

                return $invoice;
            });
        } catch (\Throwable $e) {
            // Sin confirmación no debe quedar el soporte suelto en la carpeta del corte
            $this->uploads->forModule('Cortes')->delete($file['file_path']);
            throw $e;
        }

        // Después del commit: aviso a Contabilidad para que la apruebe
        $this->accounting->pending('cortes', $invoice->corte->project_id, $this->accountingPayload($invoice));

        return $invoice;
    }

    public function canReviewAccounting(CorteInvoice $invoice, User $user): bool
    {
        return $invoice->accounting_status === CorteInvoice::ACCOUNTING_PENDIENTE
            && CorteWorkflow::canApproveAccounting($user)
            && $user->canAccessProject($invoice->corte->project_id);
    }

    /**
     * Contabilidad aprueba la factura cargada en Multifox (pasa a cuentas por pagar: la nota crédito descuenta de la
     * factura que afecta y la de reemplazo sustituye a la anterior) o la devuelve a quien la carga en Multifox, que
     * debe volver a confirmarla con el soporte corregido. Contabilidad registra aquí el valor de la factura
     * (obligatorio al aprobar si aún no lo tiene).
     */
    public function reviewAccounting(CorteInvoice $invoice, string $decision, ?string $comment, User $user, ?float $amount = null): CorteInvoice
    {
        $comment = $comment !== null ? (trim($comment) ?: null) : null;
        if (!$this->canReviewAccounting($invoice, $user)) {
            throw new InvalidArgumentException('Esta factura no está pendiente de la aprobación de Contabilidad o no puedes aprobarla.');
        }
        if ($decision === 'devolver' && !$comment) {
            throw new InvalidArgumentException('Indica el motivo de la devolución para que sepan qué corregir.');
        }
        if ($decision === 'aprobar' && !$amount && $invoice->amount === null) {
            throw new InvalidArgumentException('Indica el valor de la factura: con él pasa a cuentas por pagar.');
        }

        // Quien la cargó en Multifox: recibe la devolución (se limpia al devolver)
        $multifoxEmail = $invoice->multifoxConfirmer?->email;

        $invoice = DB::transaction(function () use ($invoice, $decision, $comment, $user, $amount) {
            $invoice = CorteInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->accounting_status !== CorteInvoice::ACCOUNTING_PENDIENTE) {
                throw new InvalidArgumentException('Otro usuario ya revisó esta factura.');
            }
            if ($amount) {
                $invoice->update(['amount' => $amount]);
                FacturacionInvoice::where('corte_invoice_id', $invoice->id)->update(['amount' => $amount]);
            }

            $label = $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number}";

            if ($decision === 'aprobar') {
                $invoice->update([
                    'accounting_status' => CorteInvoice::ACCOUNTING_APROBADA,
                    'accounting_by' => $user->id,
                    'accounting_at' => now(),
                    'accounting_comment' => $comment,
                ]);
                $this->pagos->registerFromCorteInvoice($invoice, $user);
                $history = "{$label} aprobada por Contabilidad. Pasa a cuentas por pagar." . ($comment ? " {$comment}" : '');
            } elseif (!$invoice->requiresMultifox()) {
                // La nota crédito no pasa por Multifox: queda devuelta hasta que se corrija (eliminar y asociar de nuevo)
                $invoice->update([
                    'accounting_status' => CorteInvoice::ACCOUNTING_DEVUELTA,
                    'accounting_by' => $user->id,
                    'accounting_at' => now(),
                    'accounting_comment' => $comment,
                ]);
                $history = "{$label} devuelta por Contabilidad. {$comment}";
            } else {
                // Vuelve a Multifox: el soporte anterior queda en el historial y se debe cargar de nuevo
                $invoice->update([
                    'accounting_status' => CorteInvoice::ACCOUNTING_DEVUELTA,
                    'accounting_by' => $user->id,
                    'accounting_at' => now(),
                    'accounting_comment' => $comment,
                    'multifox_confirmed_at' => null,
                    'multifox_confirmed_by' => null,
                    'multifox_file_name' => null,
                    'multifox_file_path' => null,
                    'multifox_file_size' => null,
                    'multifox_file_mime' => null,
                ]);
                $history = "{$label} devuelta por Contabilidad a Multifox. {$comment}";
            }

            $invoice->corte->history()->create([
                'action' => $decision === 'aprobar' ? CorteHistory::ACTION_CONTABILIDAD_APROBADA : CorteHistory::ACTION_CONTABILIDAD_DEVUELTA,
                'comment' => $history,
                'user_id' => $user->id,
            ]);

            return $invoice;
        });

        $projectId = $invoice->corte->project_id;
        $payload = $this->accountingPayload($invoice);

        if ($decision === 'aprobar') {
            // El flujo: residente que radicó el corte, residentes configurados y quien la cargó en Multifox
            $flow = collect([$invoice->corte->creator?->email, $multifoxEmail])
                ->merge(MailRecipients::for('cortes', 'residentes', $projectId, config('cortes.residentes', [])));
            $this->accounting->approved('cortes', $projectId, $flow, $payload, $comment, $user->name);
        } else {
            // Quienes cargan en Multifox: permiso cortes.multifox (o su correo) y quien la había confirmado
            $multifox = $this->accounting->usersWith(['cortes.multifox', 'correos.cortes.multifox'], $projectId)->push($multifoxEmail);
            $this->accounting->returned('cortes', $projectId, $multifox, [...$payload, 'returned_to' => 'Multifox'], (string) $comment, $user->name);
        }

        return $invoice;
    }

    protected function accountingPayload(CorteInvoice $invoice): array
    {
        $invoice->loadMissing(['corte.project:id,name', 'corte.tower:id,name', 'corte.contractor:id,name,nit', 'corte.contract:id,contract_code', 'corte.creator:id,name,email']);
        $corte = $invoice->corte;

        return [
            'module' => 'Registro de Corte',
            'reference' => $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number} · {$corte->reference()}",
            'details' => [
                'Documento' => $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number}",
                'Documento que afecta' => $invoice->related_document,
                'Valor' => $invoice->amount !== null ? '$' . number_format((float) $invoice->amount, 0, ',', '.') : null,
                'Corte' => $corte->reference(),
                'Proveedor' => $corte->contractor?->name,
                'Proyecto / Torre' => trim(($corte->project?->name ?? '—') . ($corte->tower ? ' · ' . $corte->tower->name : '')),
                'Radicado por' => $corte->creator?->name,
            ],
            'cta_url' => config('cortes.frontend_url') . '/app/contratacion/cortes?corte=' . $corte->id,
            // Pestaña que abre el botón del correo según a quién le llega
            'tabs' => ['pending' => 'accounting', 'returned' => 'multifox'],
        ];
    }
}
