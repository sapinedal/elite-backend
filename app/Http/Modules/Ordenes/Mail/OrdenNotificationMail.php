<?php

namespace App\Http\Modules\Ordenes\Mail;

use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Service\OrdenService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Un solo correo para todos los eventos del flujo de órdenes; el evento define asunto, textos, color y botón.
 * Plantilla: emails/ordenes/notification (sobre el layout de marca emails/layouts/inverconstruccion).
 */
class OrdenNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';           // Nueva orden: Director debe aprobar
    public const EVENT_RESUBMITTED = 'resubmitted';       // Orden corregida: Director debe aprobar
    public const EVENT_CONFIRMATION = 'confirmation';     // Confirmación al residente
    public const EVENT_PENDING_REVIEW = 'pending_review'; // Pasó a la siguiente aprobación
    public const EVENT_ADVANCED = 'advanced';             // Aviso al residente de que avanzó
    public const EVENT_RETURNED = 'returned';             // Devuelta
    public const EVENT_APPROVED = 'approved';             // Aprobación final (residente y aprobadores)
    public const EVENT_STEP_PENDING = 'step_pending';     // Responsable del siguiente documento (Almacén / Facturación)
    public const EVENT_COMPLETED = 'completed';           // Entrada cargada: orden completada

    protected const TONES = [
        'action' => ['#c2701f', '#fdf1e4'],
        'info' => ['#004C6C', '#e6f0f5'],
        'success' => ['#15803d', '#e7f6ec'],
        'danger' => ['#b91c1c', '#fdecec'],
    ];

    /**
     * @param string|null $stage Etapa o documento del evento: el que sigue (submitted / pending_review / step_pending)
     *                           o el que actuó (advanced / returned / approved / completed).
     */
    public function __construct(
        public Orden $orden,
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
        $orden = $this->orden;
        $orden->loadMissing(['project:id,name', 'tower:id,name', 'creator:id,name']);
        if ($orden->exists) {
            $orden->load(['history.user:id,name', 'documents.uploader:id,name']);
        }

        $texts = $this->texts();
        [$tone, $toneSoft] = self::TONES[$texts['tone']];

        return new Content(
            view: 'emails.ordenes.notification',
            with: [
                'headline' => $texts['headline'],
                'preheader' => $texts['intro'],
                'badge' => $texts['badge'],
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $texts['intro'],
                'commentLabel' => $texts['commentLabel'],
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'ctaUrl' => config('ordenes.frontend_url') . '/app/contratacion/ordenes?orden=' . $orden->id,
                'ctaLabel' => $texts['cta'],
                'progress' => app(OrdenService::class)->progress($orden),
                'details' => $this->details(),
            ],
        );
    }

    protected function texts(): array
    {
        $o = $this->orden;
        $code = $o->code;
        $label = OrdenWorkflow::stageLabel($this->stage);
        $where = trim(($o->project?->name ?? '') . ($o->tower ? ' · ' . $o->tower->name : ''));
        $creator = $o->creator?->name ?? 'El residente';
        $what = Str::limit(Str::squish($o->description), 80);
        $nextLabel = $this->stage ? OrdenWorkflow::stageLabel(OrdenWorkflow::nextStage($this->stage)) : null;
        $previous = $this->stage ? OrdenWorkflow::previousStages($this->stage) : [];
        $previousLabel = $previous ? OrdenWorkflow::stageLabel(end($previous)) : null;
        $documentOf = fn (string $step) => $o->relationLoaded('documents') ? $o->documents->firstWhere('step', $step) : null;
        $erp = $documentOf(OrdenWorkflow::STEP_ORDEN_ERP);
        $invoice = $documentOf(OrdenWorkflow::STEP_FACTURA);

        return match ($this->event) {
            self::EVENT_SUBMITTED => [
                'subject' => "Nueva orden por aprobar: {$code}",
                'headline' => 'Nueva orden por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} registró la orden {$code} ({$what}) de {$where}. Revísala y apruébala o devuélvela desde Elite.",
                'commentLabel' => 'Observaciones del residente',
                'cta' => 'Revisar orden',
            ],
            self::EVENT_RESUBMITTED => [
                'subject' => "Orden corregida por aprobar: {$code}",
                'headline' => 'Orden corregida por aprobar',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "{$creator} corrigió y reenvió la orden {$code} de {$where}, que había sido devuelta. La aprobación comienza de nuevo.",
                'commentLabel' => 'Ajustes realizados',
                'cta' => 'Revisar orden',
            ],
            self::EVENT_CONFIRMATION => [
                'subject' => "Orden registrada: {$code}",
                'headline' => 'Orden registrada correctamente',
                'badge' => 'Registrada',
                'tone' => 'info',
                'intro' => "La orden {$code} ({$what}) quedó registrada y pendiente de aprobación del Director de Obra. Te avisaremos en cada etapa.",
                'commentLabel' => 'Observaciones',
                'cta' => 'Ver orden',
            ],
            self::EVENT_PENDING_REVIEW => [
                'subject' => "Orden pendiente de tu aprobación ({$label}): {$code}",
                'headline' => 'Orden pendiente de tu aprobación',
                'badge' => "Requiere tu aprobación · {$label}",
                'tone' => 'action',
                'intro' => "La orden {$code} ({$what}) de {$where} fue aprobada por {$previousLabel} y ahora requiere tu aprobación.",
                'commentLabel' => "Comentario de {$previousLabel}",
                'cta' => 'Revisar orden',
            ],
            self::EVENT_ADVANCED => [
                'subject' => "Orden aprobada por {$label}: {$code}",
                'headline' => "Aprobada por {$label}",
                'badge' => 'En aprobación',
                'tone' => 'success',
                'intro' => "{$label} aprobó la orden {$code} de {$where}. Continúa con la aprobación de {$nextLabel}.",
                'commentLabel' => "Comentario de {$label}",
                'cta' => 'Ver orden',
            ],
            self::EVENT_RETURNED => [
                'subject' => "Orden devuelta por {$label}: {$code}",
                'headline' => "Orden devuelta por {$label}",
                'badge' => 'Devuelta',
                'tone' => 'danger',
                'intro' => "La orden {$code} de {$where} fue devuelta. El residente debe corregirla y reenviarla desde Elite; la aprobación comenzará de nuevo con el Director de Obra.",
                'commentLabel' => 'Motivo de la devolución',
                'cta' => 'Ver orden',
            ],
            self::EVENT_APPROVED => [
                'subject' => "Orden aprobada: {$code}",
                'headline' => 'Orden aprobada',
                'badge' => 'Aprobada',
                'tone' => 'success',
                'intro' => "La orden {$code} ({$what}) completó las aprobaciones del Director de Obra y Gerencia. Almacén cargará la orden de compra en el ERP.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Ver orden',
            ],
            self::EVENT_STEP_PENDING => match ($this->stage) {
                OrdenWorkflow::STEP_ORDEN_ERP => [
                    'subject' => "Cargar orden de compra en el ERP: {$code}",
                    'headline' => 'Orden aprobada: cargar en el ERP',
                    'badge' => 'Pendiente · Orden de compra ERP',
                    'tone' => 'action',
                    'intro' => "La orden {$code} ({$what}) de {$where} fue aprobada. Crea la orden de compra en el ERP y carga en Elite el archivo que genera, con su número.",
                    'commentLabel' => 'Comentario de Gerencia',
                    'cta' => 'Cargar orden de compra',
                ],
                OrdenWorkflow::STEP_FACTURA => [
                    'subject' => "Cargar factura del proveedor: {$code}",
                    'headline' => 'Pendiente factura del proveedor',
                    'badge' => 'Pendiente · Factura del proveedor',
                    'tone' => 'action',
                    'intro' => "Almacén cargó la orden de compra " . ($erp ? "N.º {$erp->number} " : '') . "de la orden {$code} ({$what}). Carga en Elite la factura del proveedor.",
                    'commentLabel' => 'Observación de Almacén',
                    'cta' => 'Cargar factura',
                ],
                default => [
                    'subject' => "Cargar entrada de almacén: {$code}",
                    'headline' => 'Pendiente entrada de almacén',
                    'badge' => 'Pendiente · Entrada de almacén',
                    'tone' => 'action',
                    'intro' => "Facturación cargó la factura del proveedor " . ($invoice ? "N.º {$invoice->number} " : '') . "de la orden {$code} ({$what}). Registra la entrada y carga el soporte en Elite.",
                    'commentLabel' => 'Observación de Facturación',
                    'cta' => 'Cargar entrada',
                ],
            },
            self::EVENT_COMPLETED => [
                'subject' => "Orden completada: {$code}",
                'headline' => 'Orden completada',
                'badge' => 'Completada',
                'tone' => 'success',
                'intro' => "Se cargó la entrada de almacén de la orden {$code} ({$what}). La orden quedó completada con su orden de compra, factura y entrada.",
                'commentLabel' => 'Observación de Almacén',
                'cta' => 'Ver orden',
            ],
        };
    }

    protected function details(): array
    {
        $o = $this->orden;
        $details = [
            'Orden' => $o->code,
            'Descripción' => $o->description,
            'Presupuesto' => trim(($o->project?->name ?? '—') . ($o->tower ? ' · ' . $o->tower->name : '')),
            'Capítulo' => $o->chapter,
            'Actividad' => $o->activity,
            'Registrada por' => $o->creator?->name ?? '—',
            'Fecha de registro' => $o->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—',
        ];

        if ($o->relationLoaded('documents')) {
            foreach ($o->documents as $document) {
                $details[$document->stepLabel()] = "N.º {$document->number}"
                    . ($document->amount !== null ? ' · $' . number_format((float) $document->amount, 0, ',', '.') : '');
            }
        }

        return $details + ['Estado' => OrdenWorkflow::statusLabel($o->status)];
    }
}
