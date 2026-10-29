<?php

namespace App\Http\Modules\CuentasCobro\Mail;

use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroService;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para todos los eventos del flujo de cuentas de cobro; el evento define asunto, textos, color y botón.
 * Plantilla: emails/cuentas_cobro/notification (sobre el layout de marca emails/layouts/inverconstruccion).
 */
class CuentaCobroNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';
    public const EVENT_RESUBMITTED = 'resubmitted';
    public const EVENT_CONFIRMATION = 'confirmation';
    public const EVENT_PENDING_REVIEW = 'pending_review';
    public const EVENT_ADVANCED = 'advanced';
    public const EVENT_RETURNED = 'returned';
    public const EVENT_APPROVED = 'approved';
    public const EVENT_MULTIFOX_REVIEW = 'multifox_review'; // Paula decide si requiere Multifox
    public const EVENT_IN_PAYMENTS = 'in_payments';         // Pasó al cuadro de pagos

    protected const TONES = [
        'action' => ['#c2701f', '#fdf1e4'],
        'info' => ['#004C6C', '#e6f0f5'],
        'success' => ['#15803d', '#e7f6ec'],
        'danger' => ['#b91c1c', '#fdecec'],
    ];

    public function __construct(
        public CuentaCobro $cuenta,
        public string $event,
        public ?string $stage = null,
        public ?string $comment = null,
        public ?string $subjectPrefix = null, // Solo para los correos de ejemplo
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: trim(($this->subjectPrefix ?? '') . ' ' . $this->texts()['subject']),
        );
    }

    public function content(): Content
    {
        $cuenta = $this->cuenta;
        $cuenta->loadMissing(['tipo', 'project:id,name', 'tower:id,name', 'creator:id,name', 'contract:id,contract_code', 'contractor:id,name,nit']);
        if ($cuenta->exists) {
            $cuenta->load(['history.user:id,name', 'adjuntos']);
        }

        $texts = $this->texts();
        [$tone, $toneSoft] = self::TONES[$texts['tone']];

        return new Content(
            view: 'emails.cuentas_cobro.notification',
            with: [
                'headline' => $texts['headline'],
                'preheader' => $texts['intro'],
                'badge' => $texts['badge'],
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $texts['intro'],
                'commentLabel' => $texts['commentLabel'],
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'ctaUrl' => config('cuentas_cobro.frontend_url') . '/app/contratacion/cuentas-cobro?cuenta=' . $cuenta->id,
                'ctaLabel' => $texts['cta'],
                'progress' => app(CuentaCobroService::class)->progress($cuenta),
                'details' => $this->details(),
            ],
        );
    }

    protected function texts(): array
    {
        $c = $this->cuenta;
        $ref = $c->reference();
        $label = CuentaCobroWorkflow::stageLabel($this->stage);
        $creator = $c->creator?->name ?? 'El residente';
        $supplier = $c->contractor?->name ?? 'el proveedor';
        $amount = '$' . number_format((float) $c->amount, 0, ',', '.');
        $nextLabel = $this->stage ? CuentaCobroWorkflow::stageLabel(CuentaCobroWorkflow::nextStage($this->stage)) : null;
        $previous = $this->stage ? CuentaCobroWorkflow::previousStages($this->stage) : [];
        $previousLabel = $previous ? CuentaCobroWorkflow::stageLabel(end($previous)) : null;

        return match ($this->event) {
            self::EVENT_SUBMITTED => [
                'subject' => "Nueva cuenta de cobro por aprobar: {$ref}",
                'headline' => 'Nueva cuenta de cobro por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} radicó la cuenta de cobro {$ref} de {$supplier} por {$amount}. Revísala y apruébala o devuélvela desde Elite.",
                'commentLabel' => 'Observaciones',
                'cta' => 'Revisar cuenta de cobro',
            ],
            self::EVENT_RESUBMITTED => [
                'subject' => "Cuenta de cobro corregida por aprobar: {$ref}",
                'headline' => 'Cuenta de cobro corregida por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} corrigió y reenvió la cuenta de cobro {$ref} de {$supplier}, que había sido devuelta. La aprobación comienza de nuevo.",
                'commentLabel' => 'Ajustes realizados',
                'cta' => 'Revisar cuenta de cobro',
            ],
            self::EVENT_CONFIRMATION => [
                'subject' => "Cuenta de cobro radicada: {$ref}",
                'headline' => 'Cuenta de cobro radicada correctamente',
                'badge' => 'Radicada',
                'tone' => 'info',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} por {$amount} quedó radicada y pendiente de aprobación del Director de Obra. Te avisaremos en cada etapa.",
                'commentLabel' => 'Observaciones',
                'cta' => 'Ver cuenta de cobro',
            ],
            self::EVENT_PENDING_REVIEW => [
                'subject' => "Cuenta de cobro pendiente de tu aprobación ({$label}): {$ref}",
                'headline' => 'Cuenta de cobro pendiente de tu aprobación',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} por {$amount} fue aprobada por {$previousLabel} y ahora requiere tu aprobación.",
                'commentLabel' => "Comentario de {$previousLabel}",
                'cta' => 'Revisar cuenta de cobro',
            ],
            self::EVENT_ADVANCED => [
                'subject' => "Cuenta de cobro aprobada por {$label}: {$ref}",
                'headline' => "Aprobada por {$label}",
                'badge' => 'En aprobación',
                'tone' => 'success',
                'intro' => "{$label} aprobó la cuenta de cobro {$ref}. Continúa con la aprobación de {$nextLabel}.",
                'commentLabel' => "Comentario de {$label}",
                'cta' => 'Ver cuenta de cobro',
            ],
            self::EVENT_RETURNED => [
                'subject' => "Cuenta de cobro devuelta por {$label}: {$ref}",
                'headline' => "Cuenta de cobro devuelta por {$label}",
                'badge' => 'Devuelta',
                'tone' => 'danger',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} fue devuelta. Debe corregirse y reenviarse desde Elite; la aprobación comenzará de nuevo con el Director de Obra.",
                'commentLabel' => 'Motivo de la devolución',
                'cta' => 'Ver cuenta de cobro',
            ],
            self::EVENT_APPROVED => [
                'subject' => "Cuenta de cobro aprobada: {$ref}",
                'headline' => 'Cuenta de cobro aprobada',
                'badge' => 'Aprobada',
                'tone' => 'success',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} por {$amount} completó las aprobaciones del Director de Obra y Gerencia. Se revisará si requiere Multifox antes de pasar al cuadro de pagos.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Ver cuenta de cobro',
            ],
            self::EVENT_MULTIFOX_REVIEW => [
                'subject' => "Cuenta de cobro aprobada, ¿requiere Multifox?: {$ref}",
                'headline' => '¿Requiere Multifox?',
                'badge' => 'Pendiente · Multifox',
                'tone' => 'action',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} por {$amount} fue aprobada. Indica en Elite si requiere cargue en Multifox: si no, pasa de una vez al cuadro de pagos; si sí, pasa cuando la cargues.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Revisar cuenta de cobro',
            ],
            self::EVENT_IN_PAYMENTS => [
                'subject' => "Cuenta de cobro en cuadro de pagos: {$ref}",
                'headline' => 'Cuenta de cobro en cuadro de pagos',
                'badge' => 'En cuadro de pagos',
                'tone' => 'success',
                'intro' => "La cuenta de cobro {$ref} de {$supplier} pasó al cuadro de pagos por {$amount}"
                    . ($c->requires_multifox ? ' después de cargarse en Multifox' . ($c->multifox_number ? " (N.º {$c->multifox_number})" : '') . '.' : ' (no requirió Multifox).'),
                'commentLabel' => 'Observación',
                'cta' => 'Ver cuenta de cobro',
            ],
        };
    }

    protected function details(): array
    {
        $c = $this->cuenta;

        return array_filter([
            'Cuenta de cobro' => "{$c->code} · N.º {$c->document_number}",
            'Tipo' => $c->tipo?->name,
            'Proveedor' => $c->contractor ? $c->contractor->name . ($c->contractor->nit ? " (NIT {$c->contractor->nit})" : '') : '—',
            'Proyecto' => trim(($c->project?->name ?? '—') . ($c->tower ? ' · ' . $c->tower->name : '')),
            'Contrato' => $c->contract?->contract_code,
            'Valor' => '$' . number_format((float) $c->amount, 0, ',', '.'),
            'Descripción' => $c->description,
            'Adjuntos' => $c->relationLoaded('adjuntos') && $c->adjuntos->isNotEmpty() ? $c->adjuntos->count() . ' archivo(s)' : null,
            'Multifox' => $c->multifox_number ? "N.º {$c->multifox_number}" : null,
            'Radicada por' => $c->creator?->name ?? '—',
            'Fecha de radicado' => $c->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—',
            'Estado' => CuentaCobroWorkflow::statusLabel($c->status),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
