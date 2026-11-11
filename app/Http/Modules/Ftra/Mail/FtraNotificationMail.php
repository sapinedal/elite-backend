<?php

namespace App\Http\Modules\Ftra\Mail;

use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Service\FtraWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo del flujo FTRA (mismo diseño que los de Cortes). El evento define asunto, textos, color y botón.
 */
class FtraNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';           // Nuevo FTRA: el Supervisor debe revisar
    public const EVENT_RESUBMITTED = 'resubmitted';       // FTRA corregido: el Supervisor revisa de nuevo
    public const EVENT_PENDING_REVIEW = 'pending_review'; // Recibido por el Supervisor: el Director debe revisar
    public const EVENT_ADVANCED = 'advanced';             // Aviso al residente de que avanzó
    public const EVENT_RETURNED = 'returned';             // Devuelto al residente
    public const EVENT_APPROVED = 'approved';             // Recibido a satisfacción por ambos: fin del flujo

    protected const TONES = [
        'action' => ['#c2701f', '#fdf1e4'],
        'info' => ['#004C6C', '#e6f0f5'],
        'success' => ['#15803d', '#e7f6ec'],
        'danger' => ['#b91c1c', '#fdecec'],
    ];

    /**
     * @param string|null $stage Etapa que debe revisar (submitted / pending_review) o la que actuó (advanced / returned / approved).
     */
    public function __construct(
        public FtraRecord $record,
        public string $event,
        public ?string $stage = null,
        public ?string $comment = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $this->texts()['subject'],
        );
    }

    public function content(): Content
    {
        $record = $this->record;
        $record->loadMissing(['project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'format:id,name,code', 'registeredBy:id,name']);

        $texts = $this->texts();
        [$tone, $toneSoft] = self::TONES[$texts['tone']];

        return new Content(
            view: 'emails.ftra.notification',
            with: [
                'headline' => $texts['headline'],
                'preheader' => $texts['intro'],
                'badge' => $texts['badge'],
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $texts['intro'],
                'commentLabel' => $texts['commentLabel'],
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'ctaUrl' => $this->ctaUrl(),
                'ctaLabel' => $texts['cta'],
                'creatorName' => $record->registeredBy?->name,
                'progress' => FtraWorkflow::progress($record),
                'details' => $this->details(),
            ],
        );
    }

    /** Los revisores van directo a su pantalla de firma; los demás al seguimiento. */
    protected function ctaUrl(): string
    {
        $base = config('ftra.frontend_url') . '/app/ftra';

        return match (true) {
            in_array($this->event, [self::EVENT_SUBMITTED, self::EVENT_RESUBMITTED], true) => "{$base}/revision/{$this->record->id}",
            $this->event === self::EVENT_PENDING_REVIEW => "{$base}/aprobacion/{$this->record->id}",
            default => "{$base}/seguimiento?registro={$this->record->id}",
        };
    }

    protected function texts(): array
    {
        $r = $this->record;
        $code = $r->reference();
        $where = trim(($r->project?->name ?? '') . ($r->tower ? ' · ' . $r->tower->name : ''));
        $format = $r->format?->name ?? 'FTRA';
        $creator = $r->registeredBy?->name ?? 'El residente';
        $stageLabel = FtraWorkflow::stageLabel($this->stage);
        $nextLabel = $this->stage ? FtraWorkflow::stageLabel(FtraWorkflow::nextStage($this->stage)) : null;

        return match ($this->event) {
            self::EVENT_SUBMITTED => [
                'subject' => "Nuevo FTRA por revisar: {$code}",
                'headline' => 'Nuevo FTRA por revisar',
                'badge' => 'Requiere tu revisión · Supervisor',
                'tone' => 'action',
                'intro' => "{$creator} registró el {$format} del apartamento {$r->apartamento} ({$where}). Revísalo y recíbelo a satisfacción o devuélvelo desde Elite.",
                'commentLabel' => 'Observaciones del residente',
                'cta' => 'Revisar FTRA',
            ],
            self::EVENT_RESUBMITTED => [
                'subject' => "FTRA corregido por revisar: {$code}",
                'headline' => 'FTRA corregido por revisar',
                'badge' => 'Requiere tu revisión · Supervisor',
                'tone' => 'action',
                'intro' => "{$creator} corrigió y reenvió el {$format} del apartamento {$r->apartamento} ({$where}), que había sido devuelto. La revisión comienza de nuevo.",
                'commentLabel' => 'Ajustes realizados',
                'cta' => 'Revisar FTRA',
            ],
            self::EVENT_PENDING_REVIEW => [
                'subject' => "FTRA pendiente de tu aprobación (Director de Obra): {$code}",
                'headline' => 'FTRA pendiente de tu aprobación',
                'badge' => 'Requiere tu revisión · Director de Obra',
                'tone' => 'action',
                'intro' => "El Supervisor recibió a satisfacción el {$format} del apartamento {$r->apartamento} ({$where}). Ahora requiere tu revisión como Director de Obra.",
                'commentLabel' => 'Comentario del Supervisor',
                'cta' => 'Revisar FTRA',
            ],
            self::EVENT_ADVANCED => [
                'subject' => "FTRA recibido por {$stageLabel}: {$code}",
                'headline' => "Recibido a satisfacción por {$stageLabel}",
                'badge' => 'En revisión',
                'tone' => 'success',
                'intro' => "{$stageLabel} recibió a satisfacción el {$format} del apartamento {$r->apartamento}. Continúa con la revisión de {$nextLabel}.",
                'commentLabel' => "Comentario de {$stageLabel}",
                'cta' => 'Ver FTRA',
            ],
            self::EVENT_RETURNED => [
                'subject' => "FTRA devuelto por {$stageLabel}: {$code}",
                'headline' => "FTRA devuelto por {$stageLabel}",
                'badge' => 'Devuelto',
                'tone' => 'danger',
                'intro' => "El {$format} del apartamento {$r->apartamento} ({$where}) fue devuelto. El residente debe corregirlo y reenviarlo desde Elite; la revisión comenzará de nuevo con el Supervisor.",
                'commentLabel' => 'Motivo de la devolución',
                'cta' => 'Ver FTRA',
            ],
            self::EVENT_APPROVED => [
                'subject' => "FTRA aprobado: {$code}",
                'headline' => 'FTRA recibido a satisfacción',
                'badge' => 'Aprobado',
                'tone' => 'success',
                'intro' => "El {$format} del apartamento {$r->apartamento} ({$where}) fue recibido a satisfacción por el Supervisor y el Director de Obra. El flujo terminó.",
                'commentLabel' => 'Comentario del Director de Obra',
                'cta' => 'Ver FTRA',
            ],
        };
    }

    protected function details(): array
    {
        $r = $this->record;
        $contractor = $r->contractor
            ? $r->contractor->name . ($r->contractor->nit ? " · NIT {$r->contractor->nit}" : '')
            : '—';

        return [
            'Formato' => trim(($r->format?->code ? "[{$r->format->code}] " : '') . ($r->format?->name ?? '—')),
            'Proyecto / Torre' => trim(($r->project?->name ?? '—') . ($r->tower ? ' · ' . $r->tower->name : '')),
            'Piso / Apartamento' => 'Piso ' . ($r->piso ?? '—') . ' · Apto ' . ($r->apartamento ?? '—'),
            'Proveedor' => $contractor,
            'Inspección del residente' => $r->resultado_inspeccion ?? '—',
            'Registrado por' => $r->registeredBy?->name ?? '—',
            'Fecha de registro' => $r->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—',
            'Estado' => FtraWorkflow::statusLabel($r->status),
        ];
    }
}
