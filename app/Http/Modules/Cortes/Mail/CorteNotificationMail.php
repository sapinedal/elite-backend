<?php

namespace App\Http\Modules\Cortes\Mail;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Service\CorteService;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para todos los eventos del flujo de cortes; el evento define asunto, textos, color y botón.
 * La plantilla es emails/cortes/notification (sobre el layout de marca emails/layouts/inverconstruccion).
 */
class CorteNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';           // Nuevo corte: Interventor debe revisar
    public const EVENT_RESUBMITTED = 'resubmitted';       // Corte corregido: Interventor debe revisar
    public const EVENT_CONFIRMATION = 'confirmation';     // Confirmación al residente
    public const EVENT_PENDING_REVIEW = 'pending_review'; // Pasó a la siguiente etapa: sus revisores
    public const EVENT_ADVANCED = 'advanced';             // Aviso al residente de que avanzó
    public const EVENT_RETURNED = 'returned';             // Devuelto
    public const EVENT_APPROVED = 'approved';             // Aprobación final (residente y revisores)
    public const EVENT_BILLING = 'billing';               // Aprobación final: facturación

    protected const TONES = [
        'action' => ['#c2701f', '#fdf1e4'],
        'info' => ['#004C6C', '#e6f0f5'],
        'success' => ['#15803d', '#e7f6ec'],
        'danger' => ['#b91c1c', '#fdecec'],
    ];

    /**
     * @param string|null $stage Etapa del evento: la que revisa (submitted / pending_review) o la que actuó (advanced / returned / approved).
     */
    public function __construct(
        public Corte $corte,
        public string $event,
        public ?string $stage = null,
        public ?string $comment = null,
        public ?string $subjectPrefix = null, // Solo para los correos de ejemplo (cortes:preview-emails)
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
        $corte = $this->corte;
        $corte->loadMissing(['project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code', 'creator:id,name']);
        if ($corte->exists) {
            $corte->load('history.user:id,name'); // Historial actualizado para la línea de tiempo
        }

        $texts = $this->texts();
        [$tone, $toneSoft] = self::TONES[$texts['tone']];

        return new Content(
            view: 'emails.cortes.notification',
            with: [
                'headline' => $texts['headline'],
                'preheader' => $texts['intro'],
                'badge' => $texts['badge'],
                'tone' => $tone,
                'toneSoft' => $toneSoft,
                'intro' => $texts['intro'],
                'commentLabel' => $texts['commentLabel'],
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'ctaUrl' => config('cortes.frontend_url') . '/app/contratacion/cortes?corte=' . $corte->id,
                'ctaLabel' => $texts['cta'],
                'progress' => app(CorteService::class)->progress($corte),
                'details' => $this->details(),
            ],
        );
    }

    /**
     * Asunto, título, texto, etiqueta, color y botón según el evento.
     */
    protected function texts(): array
    {
        $c = $this->corte;
        $code = $c->reference(); // "CORTE 3 · Contrato 2759"
        $stageLabel = CorteWorkflow::stageLabel($this->stage);
        $where = trim(($c->project?->name ?? '') . ($c->tower ? ' · ' . $c->tower->name : ''));
        $creator = $c->creator?->name ?? 'El residente';
        $nextLabel = $this->stage ? CorteWorkflow::stageLabel(CorteWorkflow::nextStage($this->stage)) : null;
        $previous = $this->stage ? CorteWorkflow::previousStages($this->stage) : [];
        $previousLabel = $previous ? CorteWorkflow::stageLabel(end($previous)) : null;

        return match ($this->event) {
            self::EVENT_SUBMITTED => [
                'subject' => "Nuevo corte por revisar: {$code}",
                'headline' => 'Nuevo corte por revisar',
                'badge' => 'Requiere tu revisión · Interventor',
                'tone' => 'action',
                'intro' => "{$creator} radicó el {$code} de {$where}. Revísalo y apruébalo o devuélvelo desde Elite.",
                'commentLabel' => 'Observaciones del residente',
                'cta' => 'Revisar corte',
            ],
            self::EVENT_RESUBMITTED => [
                'subject' => "Corte corregido por revisar: {$code}",
                'headline' => 'Corte corregido por revisar',
                'badge' => 'Requiere tu revisión · Interventor',
                'tone' => 'action',
                'intro' => "{$creator} corrigió y reenvió el {$code} de {$where}, que había sido devuelto. La revisión comienza de nuevo.",
                'commentLabel' => 'Ajustes realizados',
                'cta' => 'Revisar corte',
            ],
            self::EVENT_CONFIRMATION => [
                'subject' => "Corte radicado: {$code}",
                'headline' => 'Corte radicado correctamente',
                'badge' => 'Radicado',
                'tone' => 'info',
                'intro' => "El {$code} de {$where} quedó radicado y pendiente de revisión del Interventor. Te avisaremos en cada etapa.",
                'commentLabel' => 'Observaciones',
                'cta' => 'Ver corte',
            ],
            self::EVENT_PENDING_REVIEW => [
                'subject' => "Corte pendiente de tu aprobación ({$stageLabel}): {$code}",
                'headline' => 'Corte pendiente de tu aprobación',
                'badge' => "Requiere tu revisión · {$stageLabel}",
                'tone' => 'action',
                'intro' => "El {$code} de {$where} fue aprobado por {$previousLabel} y ahora requiere tu revisión como {$stageLabel}.",
                'commentLabel' => "Comentario de {$previousLabel}",
                'cta' => 'Revisar corte',
            ],
            self::EVENT_ADVANCED => [
                'subject' => "Corte aprobado por {$stageLabel}: {$code}",
                'headline' => "Aprobado por {$stageLabel}",
                'badge' => 'En revisión',
                'tone' => 'success',
                'intro' => "{$stageLabel} aprobó el {$code} de {$where}. Continúa con la revisión de {$nextLabel}.",
                'commentLabel' => "Comentario de {$stageLabel}",
                'cta' => 'Ver corte',
            ],
            self::EVENT_RETURNED => [
                'subject' => "Corte devuelto por {$stageLabel}: {$code}",
                'headline' => "Corte devuelto por {$stageLabel}",
                'badge' => 'Devuelto',
                'tone' => 'danger',
                'intro' => "El {$code} de {$where} fue devuelto. El residente debe corregirlo y reenviarlo desde Elite; la revisión comenzará de nuevo con el Interventor.",
                'commentLabel' => 'Motivo de la devolución',
                'cta' => 'Ver corte',
            ],
            self::EVENT_APPROVED => [
                'subject' => "Corte aprobado: {$code}",
                'headline' => 'Corte aprobado',
                'badge' => 'Aprobado',
                'tone' => 'success',
                'intro' => "El {$code} de {$where} completó las tres revisiones (Interventor, Director de Obra y Gerencia) y quedó aprobado.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Ver corte',
            ],
            self::EVENT_BILLING => [
                'subject' => "Corte aprobado para facturación: {$code}",
                'headline' => 'Corte listo para facturación',
                'badge' => 'Pendiente cargar factura',
                'tone' => 'success',
                'intro' => "El {$code} del proveedor " . ($c->contractor?->name ?? '—') . " completó las tres aprobaciones. Carga la factura en Facturas del mes; el residente responsable la asociará al corte y se notificará para su registro en Multifox.",
                'commentLabel' => 'Comentario de Gerencia',
                'cta' => 'Cargar factura',
            ],
        };
    }

    /**
     * Filas de la tabla de datos del corte.
     */
    protected function details(): array
    {
        $c = $this->corte;
        $contractor = $c->contractor
            ? $c->contractor->name . ($c->contractor->nit ? " · NIT {$c->contractor->nit}" : '')
            : '—';

        return [
            'Código del corte' => $c->code,
            'Proyecto / Torre' => trim(($c->project?->name ?? '—') . ($c->tower ? ' · ' . $c->tower->name : '')),
            'Proveedor' => $contractor,
            'Contrato' => $c->contract?->contract_code ?? '—',
            'Archivo' => $c->file_name . ' (' . $this->formatBytes((int) $c->file_size) . ')',
            'Radicado por' => $c->creator?->name ?? '—',
            'Fecha de radicación' => $c->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?? '—',
            'Estado' => CorteWorkflow::statusLabel($c->status),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), $i ? 1 : 0) . ' ' . $units[$i];
    }
}
