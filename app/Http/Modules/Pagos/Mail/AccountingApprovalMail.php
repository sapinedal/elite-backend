<?php

namespace App\Http\Modules\Pagos\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de la aprobación de Contabilidad, común a todos los módulos de registro (cortes, órdenes, anticipos y
 * cuentas de cobro): lo que ya se cargó en Multifox (o se completó) espera que Contabilidad lo apruebe para pasar
 * a cuentas por pagar.
 *
 *   pending  -> Contabilidad (correos.<módulo>.aprobacion_contable): debe aprobar o devolver
 *   approved -> el flujo (residente y quien cargó en Multifox): pasó a cuentas por pagar
 *   returned -> quien carga en Multifox (cortes.multifox o el responsable del módulo): debe corregir
 */
class AccountingApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_PENDING = 'pending';
    public const EVENT_APPROVED = 'approved';
    public const EVENT_RETURNED = 'returned';

    protected const TONES = [
        self::EVENT_PENDING => ['#c2701f', '#fdf1e4'],
        self::EVENT_APPROVED => ['#15803d', '#e7f6ec'],
        self::EVENT_RETURNED => ['#b91c1c', '#fdecec'],
    ];

    /**
     * @param array{module: string, reference: string, details: array<string, string|null>, cta_url: string, returned_to?: string} $payload
     */
    public function __construct(
        public string $event,
        public array $payload,
        public ?string $comment = null,
        public ?string $actor = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            self::EVENT_PENDING => 'Aprobar en Contabilidad',
            self::EVENT_APPROVED => 'Aprobado por Contabilidad',
            default => 'Devuelto por Contabilidad',
        };

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: "{$subject}: {$this->payload['reference']}",
        );
    }

    /**
     * El botón abre el registro sobre la pestaña donde le toca actuar a quien recibe el correo
     * (payload.tabs: Contabilidad al quedar pendiente, Multifox / Almacén al devolverse).
     */
    public function ctaUrl(): string
    {
        $url = $this->payload['cta_url'];
        $tab = $this->payload['tabs'][$this->event] ?? null;

        return $tab ? $url . (str_contains($url, '?') ? '&' : '?') . 'tab=' . urlencode($tab) : $url;
    }

    public function content(): Content
    {
        [$tone, $toneSoft] = self::TONES[$this->event];
        $reference = $this->payload['reference'];
        $actor = $this->actor ?? 'Contabilidad';

        [$headline, $badge, $intro, $cta] = match ($this->event) {
            self::EVENT_PENDING => [
                'Pendiente de aprobación de Contabilidad',
                "Requiere tu aprobación · {$this->payload['module']}",
                ($this->payload['pending_intro'] ?? "{$reference} ya se cargó en Multifox.") . ' Revísalo y apruébalo para que pase a cuentas por pagar, o devuélvelo indicando qué corregir.',
                'Revisar y aprobar',
            ],
            self::EVENT_APPROVED => [
                'Aprobado por Contabilidad',
                'Pasó a cuentas por pagar',
                "{$actor} aprobó {$reference}. Ya está en cuentas por pagar.",
                'Ver en Elite',
            ],
            default => [
                'Devuelto por Contabilidad',
                'Requiere corrección',
                "{$actor} devolvió {$reference}" . (!empty($this->payload['returned_to']) ? " a {$this->payload['returned_to']}" : '')
                    . '. Corrige lo indicado y vuelve a cargarlo para que Contabilidad lo apruebe.',
                'Ver y corregir',
            ],
        };

        return new Content(
            view: 'emails.pagos.accounting-approval',
            with: [
                'headline' => $headline,
                'preheader' => $intro,
                'badge' => $badge,
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $intro,
                'comment' => $this->comment,
                'commentLabel' => $this->event === self::EVENT_RETURNED ? 'Motivo de la devolución' : 'Comentario',
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'details' => array_filter($this->payload['details'], fn ($value) => $value !== null && $value !== ''),
                'ctaUrl' => $this->ctaUrl(),
                'ctaLabel' => $cta,
            ],
        );
    }
}
