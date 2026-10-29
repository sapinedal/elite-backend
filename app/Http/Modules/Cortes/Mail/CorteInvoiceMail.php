<?php

namespace App\Http\Modules\Cortes\Mail;

use App\Http\Modules\Cortes\Models\CorteInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Aviso de factura cargada a un corte aprobado, para que el cargo responsable la registre en Multifox.
 * Si el archivo es liviano (cortes.invoice_attach_max_mb) se adjunta al correo.
 */
class CorteInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CorteInvoice $invoice,
        public ?string $subjectPrefix = null, // Solo para los correos de ejemplo (notificaciones:ejemplos)
        public bool $attachFile = true,
    ) {
    }

    public function envelope(): Envelope
    {
        $corte = $this->invoice->corte;

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: trim(($this->subjectPrefix ?? '') . " Cargar a Multifox: {$this->invoice->documentTypeLabel()} N.º {$this->invoice->invoice_number} · {$corte->reference()}"),
        );
    }

    public function content(): Content
    {
        $invoice = $this->invoice;
        $corte = $invoice->corte;
        $corte->loadMissing(['project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code']);

        $contractor = $corte->contractor
            ? $corte->contractor->name . ($corte->contractor->nit ? " · NIT {$corte->contractor->nit}" : '')
            : '—';

        $invoiceDetails = array_filter([
            'Tipo de documento' => $invoice->documentTypeLabel(),
            'Número' => $invoice->invoice_number,
            'Documento que afecta' => $invoice->related_document,
            'Valor' => $invoice->amount !== null ? '$' . number_format((float) $invoice->amount, 0, ',', '.') : null,
            'Archivo' => $invoice->file_name,
            'Cargado por' => $invoice->uploader?->name,
            'Fecha de cargue' => $invoice->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a'),
        ], fn ($value) => $value !== null && $value !== '');

        return new Content(
            view: 'emails.cortes.invoice',
            with: [
                'headline' => 'Nueva factura para cargar en Multifox',
                'preheader' => "{$invoice->documentTypeLabel()} N.º {$invoice->invoice_number} del {$corte->reference()} ({$contractor}).",
                'badge' => 'Pendiente de cargue en Multifox',
                'tone' => '#004C6C',
                'toneSoft' => '#e6f0f5',
                'intro' => 'Se cargó una ' . mb_strtolower($invoice->documentTypeLabel()) . " al {$corte->reference()}, que ya cuenta con las tres aprobaciones. Regístrala en Multifox con los datos a continuación y luego confirma en Elite que quedó cargada: así pasa a cuentas por pagar.",
                'observation' => $invoice->observation,
                'isAdjustment' => $invoice->related_document !== null,
                'invoiceDetails' => $invoiceDetails,
                'corteDetails' => [
                    'Corte' => $corte->code,
                    'Proveedor' => $contractor,
                    'Contrato' => $corte->contract?->contract_code ?? '—',
                    'Proyecto / Torre' => trim(($corte->project?->name ?? '—') . ($corte->tower ? ' · ' . $corte->tower->name : '')),
                ],
                'attached' => $this->shouldAttach(),
                'ctaUrl' => config('cortes.frontend_url') . '/app/contratacion/cortes?corte=' . $corte->id,
                'ctaLabel' => 'Ver corte y confirmar carga',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (!$this->shouldAttach()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('s3', $this->invoice->file_path)
                ->as($this->invoice->file_name)
                ->withMime($this->invoice->file_mime ?: 'application/octet-stream'),
        ];
    }

    protected function shouldAttach(): bool
    {
        if (!$this->attachFile || !$this->invoice->file_path) {
            return false;
        }

        $max = config('cortes.invoice_attach_max_mb') * 1024 * 1024;

        try {
            return $this->invoice->file_size > 0
                && $this->invoice->file_size <= $max
                && Storage::disk('s3')->exists($this->invoice->file_path);
        } catch (Throwable) {
            return false; // Si S3 no responde se envía el correo sin adjunto (el enlace sigue funcionando)
        }
    }
}
