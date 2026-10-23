<?php

namespace App\Http\Modules\Cortes\Mail;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo correo para todos los eventos del flujo; el evento define el asunto y el mensaje.
 */
class CorteNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENT_SUBMITTED = 'submitted';        // Nuevo corte: revisores de Interventor
    public const EVENT_RESUBMITTED = 'resubmitted';    // Corte corregido: revisores de Interventor
    public const EVENT_CONFIRMATION = 'confirmation';  // Confirmación al residente
    public const EVENT_PENDING_REVIEW = 'pending_review'; // Pasó a la siguiente etapa: sus revisores
    public const EVENT_ADVANCED = 'advanced';          // Aviso al residente de que avanzó
    public const EVENT_APPROVED = 'approved';          // Aprobación final
    public const EVENT_RETURNED = 'returned';          // Devuelto

    public function __construct(
        public Corte $corte,
        public string $event,
        public ?string $stage = null,
        public ?string $comment = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $stageLabel = CorteWorkflow::stageLabel($this->stage);

        $subject = match ($this->event) {
            self::EVENT_SUBMITTED => "Nuevo corte por revisar: {$this->corte->code}",
            self::EVENT_RESUBMITTED => "Corte corregido por revisar: {$this->corte->code}",
            self::EVENT_CONFIRMATION => "Corte radicado: {$this->corte->code}",
            self::EVENT_PENDING_REVIEW => "Corte pendiente de tu revisión ({$stageLabel}): {$this->corte->code}",
            self::EVENT_ADVANCED => "Corte aprobado por {$stageLabel}: {$this->corte->code}",
            self::EVENT_APPROVED => "Corte aprobado: {$this->corte->code}",
            self::EVENT_RETURNED => "Corte devuelto por {$stageLabel}: {$this->corte->code}",
            default => "Registro de corte: {$this->corte->code}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $this->corte->loadMissing(['project:id,name', 'tower:id,name', 'contractor:id,name', 'contract:id,contract_code', 'creator:id,name']);
        $stageLabel = CorteWorkflow::stageLabel($this->stage);

        $message = match ($this->event) {
            self::EVENT_SUBMITTED => 'Se radicó un nuevo corte que requiere tu revisión como Interventor.',
            self::EVENT_RESUBMITTED => 'El residente corrigió y reenvió un corte que había sido devuelto. Requiere tu revisión como Interventor.',
            self::EVENT_CONFIRMATION => 'Tu corte fue radicado correctamente y quedó pendiente de revisión del Interventor.',
            self::EVENT_PENDING_REVIEW => "El corte avanzó en el flujo y requiere tu revisión como {$stageLabel}.",
            self::EVENT_ADVANCED => "Tu corte fue aprobado por {$stageLabel} y continúa a la siguiente etapa.",
            self::EVENT_APPROVED => 'El corte completó todas las revisiones y quedó aprobado.',
            self::EVENT_RETURNED => "El corte fue devuelto por {$stageLabel}. El residente debe corregirlo y reenviarlo.",
            default => '',
        };

        return new Content(
            view: 'emails.cortes.notification',
            with: [
                'headline' => $this->envelope()->subject,
                'intro' => $message,
                'isReturned' => $this->event === self::EVENT_RETURNED,
                'statusLabel' => CorteWorkflow::statusLabel($this->corte->status),
                'appUrl' => config('cortes.frontend_url') . '/app/contratacion/cortes?corte=' . $this->corte->id,
            ],
        );
    }
}
