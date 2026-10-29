<?php

namespace App\Http\Modules\Anticipos\Mail;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Service\AnticipoService;
use App\Http\Modules\Anticipos\Service\AnticipoWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para todos los eventos del flujo de anticipos; el evento define asunto, textos, color y botón.
 * Plantilla: emails/anticipos/notification (sobre el layout de marca emails/layouts/inverconstruccion).
 */
class AnticipoNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';           // Nuevo anticipo: Director debe aprobar
    public const EVENT_RESUBMITTED = 'resubmitted';       // Anticipo corregido: Director debe aprobar
    public const EVENT_CONFIRMATION = 'confirmation';     // Confirmación al residente
    public const EVENT_PENDING_REVIEW = 'pending_review'; // Pasó a la siguiente aprobación
    public const EVENT_ADVANCED = 'advanced';             // Aviso al residente de que avanzó
    public const EVENT_RETURNED = 'returned';             // Devuelto
    public const EVENT_APPROVED = 'approved';             // Aprobación final (residente y aprobadores)
    public const EVENT_ACCOUNTING = 'accounting';         // Contabilidad debe generarlo en Multifox
    public const EVENT_COMPLETED = 'completed';           // Generado en Multifox: pasó al cuadro de pagos

    protected const TONES = [
        'action' => ['#c2701f', '#fdf1e4'],
        'info' => ['#004C6C', '#e6f0f5'],
        'success' => ['#15803d', '#e7f6ec'],
        'danger' => ['#b91c1c', '#fdecec'],
    ];

    /**
     * @param string|null $stage Etapa del evento: la que sigue (submitted / pending_review / accounting)
     *                           o la que actuó (advanced / returned / approved / completed).
     */
    public function __construct(
        public Anticipo $anticipo,
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
        $anticipo = $this->anticipo;
        $anticipo->loadMissing(['project:id,name', 'tower:id,name', 'creator:id,name', 'contract:id,contract_code,contractor_name', 'contractor:id,name,nit']);
        if ($anticipo->exists) {
            $anticipo->load('history.user:id,name');
        }

        $texts = $this->texts();
        [$tone, $toneSoft] = self::TONES[$texts['tone']];

        return new Content(
            view: 'emails.anticipos.notification',
            with: [
                'headline' => $texts['headline'],
                'preheader' => $texts['intro'],
                'badge' => $texts['badge'],
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $texts['intro'],
                'commentLabel' => $texts['commentLabel'],
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'ctaUrl' => config('anticipos.frontend_url') . '/app/contratacion/anticipos?anticipo=' . $anticipo->id,
                'ctaLabel' => $texts['cta'],
                'progress' => app(AnticipoService::class)->progress($anticipo),
                'details' => $this->details(),
            ],
        );
    }

    protected function texts(): array
    {
        $a = $this->anticipo;
        $ref = $a->reference();
        $label = AnticipoWorkflow::stageLabel($this->stage);
        $where = trim(($a->project?->name ?? '') . ($a->tower ? ' · ' . $a->tower->name : ''));
        $creator = $a->creator?->name ?? 'El residente';
        $supplier = $a->contractor?->name ?? $a->contract?->contractor_name ?? 'el contratista';
        $amount = '$' . number_format((float) $a->amount, 0, ',', '.');
        $nextLabel = $this->stage ? AnticipoWorkflow::stageLabel(AnticipoWorkflow::nextStage($this->stage)) : null;
        $previous = $this->stage ? AnticipoWorkflow::previousStages($this->stage) : [];
        $previousLabel = $previous ? AnticipoWorkflow::stageLabel(end($previous)) : null;

        return match ($this->event) {
            self::EVENT_SUBMITTED => [
                'subject' => "Nuevo anticipo por aprobar: {$ref}",
                'headline' => 'Nuevo anticipo por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} radicó el anticipo {$ref} de {$supplier} por {$amount} ({$where}). Revísalo y apruébalo o devuélvelo desde Elite.",
                'commentLabel' => 'Observaciones del residente',
                'cta' => 'Revisar anticipo',
            ],
            self::EVENT_RESUBMITTED => [
                'subject' => "Anticipo corregido por aprobar: {$ref}",
                'headline' => 'Anticipo corregido por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} corrigió y reenvió el anticipo {$ref} de {$supplier}, que había sido devuelto. La aprobación comienza de nuevo.",
                'commentLabel' => 'Ajustes realizados',
                'cta' => 'Revisar anticipo',
            ],
            self::EVENT_CONFIRMATION => [
                'subject' => "Anticipo radicado: {$ref}",
                'headline' => 'Anticipo radicado correctamente',
                'badge' => 'Radicado',
                'tone' => 'info',
                'intro' => "El anticipo {$ref} de {$supplier} por {$amount} quedó radicado y pendiente de aprobación del Director de Obra. Te avisaremos en cada etapa.",
                'commentLabel' => 'Observaciones',
                'cta' => 'Ver anticipo',
            ],
            self::EVENT_PENDING_REVIEW => [
                'subject' => "Anticipo pendiente de tu aprobación ({$label}): {$ref}",
                'headline' => 'Anticipo pendiente de tu aprobación',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "El anticipo {$ref} de {$supplier} por {$amount} fue aprobado por {$previousLabel} y ahora requiere tu aprobación.",
                'commentLabel' => "Comentario de {$previousLabel}",
                'cta' => 'Revisar anticipo',
            ],
            self::EVENT_ADVANCED => [
                'subject' => "Anticipo aprobado por {$label}: {$ref}",
                'headline' => "Aprobado por {$label}",
                'badge' => 'En aprobación',
                'tone' => 'success',
                'intro' => "{$label} aprobó el anticipo {$ref}. Continúa con la aprobación de {$nextLabel}.",
                'commentLabel' => "Comentario de {$label}",
                'cta' => 'Ver anticipo',
            ],
            self::EVENT_RETURNED => [
                'subject' => "Anticipo devuelto por {$label}: {$ref}",
                'headline' => "Anticipo devuelto por {$label}",
                'badge' => 'Devuelto',
                'tone' => 'danger',
                'intro' => "El anticipo {$ref} de {$supplier} fue devuelto. El residente debe corregirlo y reenviarlo desde Elite; la aprobación comenzará de nuevo con el Director de Obra.",
                'commentLabel' => 'Motivo de la devolución',
                'cta' => 'Ver anticipo',
            ],
            self::EVENT_APPROVED => [
                'subject' => "Anticipo aprobado: {$ref}",
                'headline' => 'Anticipo aprobado',
                'badge' => 'Aprobado',
                'tone' => 'success',
                'intro' => "El anticipo {$ref} de {$supplier} por {$amount} completó las aprobaciones del Director de Obra y Gerencia. Contabilidad lo generará en Multifox.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Ver anticipo',
            ],
            self::EVENT_ACCOUNTING => [
                'subject' => "Generar anticipo en Multifox: {$ref}",
                'headline' => 'Anticipo aprobado: generar en Multifox',
                'badge' => 'Pendiente · Contabilidad',
                'tone' => 'action',
                'intro' => "El anticipo {$ref} de {$supplier} por {$amount} fue aprobado. Genéralo en Multifox y carga en Elite el soporte con su número; al hacerlo pasa al cuadro de pagos.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Cargar anticipo de Multifox',
            ],
            self::EVENT_COMPLETED => [
                'subject' => "Anticipo en cuadro de pagos: {$ref}",
                'headline' => 'Anticipo en cuadro de pagos',
                'badge' => 'Completado',
                'tone' => 'success',
                'intro' => "Contabilidad generó el anticipo {$ref} en Multifox" . ($a->multifox_number ? " (N.º {$a->multifox_number})" : '') . " y pasó al cuadro de pagos por {$amount}.",
                'commentLabel' => 'Observación de Contabilidad',
                'cta' => 'Ver anticipo',
            ],
        };
    }

    protected function details(): array
    {
        $a = $this->anticipo;

        return array_filter([
            'Anticipo' => $a->code,
            'Contrato' => $a->contract ? "{$a->contract->contract_code} · {$a->contract->contractor_name}" : '—',
            'Proveedor' => $a->contractor ? $a->contractor->name . ($a->contractor->nit ? " (NIT {$a->contractor->nit})" : '') : '—',
            'Proyecto' => trim(($a->project?->name ?? '—') . ($a->tower ? ' · ' . $a->tower->name : '')),
            'Cuenta de cobro' => "N.º {$a->document_number}",
            'Valor' => '$' . number_format((float) $a->amount, 0, ',', '.'),
            'Descripción' => $a->description,
            'Anticipo Multifox' => $a->multifox_number ? "N.º {$a->multifox_number}" : null,
            'Radicado por' => $a->creator?->name ?? '—',
            'Fecha de radicado' => $a->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—',
            'Estado' => AnticipoWorkflow::statusLabel($a->status),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
