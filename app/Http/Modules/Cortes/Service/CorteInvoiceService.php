<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Facturas de los cortes aprobados: cargue (archivo directo a S3), cambio de estado a Facturado,
 * historial y aviso al cargo que la registra en Multifox.
 */
class CorteInvoiceService
{
    public function __construct(
        protected CorteService $cortes,
        protected CorteStorageService $storage,
        protected CorteNotificationService $notifications,
    ) {
    }

    public function create(Corte $corte, array $data, User $user): CorteInvoice
    {
        if (!$this->cortes->canInvoice($corte, $user)) {
            throw new InvalidArgumentException('Solo se pueden cargar facturas a cortes con las tres aprobaciones.');
        }

        $file = $this->storage->claimUpload($data['upload_token'], $user);
        $affectsAnother = CorteInvoice::requiresRelatedDocument($data['document_type']);

        $invoice = DB::transaction(function () use ($corte, $data, $file, $user, $affectsAnother) {
            $corte = Corte::whereKey($corte->id)->lockForUpdate()->firstOrFail();

            $invoice = $corte->invoices()->create([
                'invoice_number' => trim($data['invoice_number']),
                'document_type' => $data['document_type'],
                // En la factura inicial no aplica documento relacionado aunque llegue
                'related_document' => $affectsAnother ? trim($data['related_document']) : null,
                'observation' => isset($data['observation']) ? trim($data['observation']) : null,
                'uploaded_by' => $user->id,
                ...$file,
            ]);

            if ($corte->status === CorteWorkflow::STATUS_APROBADO) {
                $corte->update(['status' => CorteWorkflow::STATUS_FACTURADO]);
            }

            $summary = $invoice->documentTypeLabel() . " N.º {$invoice->invoice_number}"
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
        });

        // Después del commit: no avisar de una factura que no quedó guardada
        $this->notifications->invoiceUploaded($invoice->fresh(['corte', 'uploader']));

        return $invoice;
    }
}
