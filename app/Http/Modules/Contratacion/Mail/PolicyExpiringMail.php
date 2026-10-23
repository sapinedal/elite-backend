<?php

namespace App\Http\Modules\Contratacion\Mail;

use App\Http\Modules\Configuracion\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PolicyExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Http\Modules\Contratacion\Models\ContratacionPolicy> $policies
     */
    public function __construct(
        public Project $project,
        public Collection $policies,
        public ?string $subjectPrefix = null, // Solo para los correos de ejemplo (cortes:preview-emails)
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trim(($this->subjectPrefix ?? '') . " Pólizas próximas a vencer - {$this->project->name} ({$this->policies->count()})"),
        );
    }

    public function content(): Content
    {
        $alertDays = config('contratacion.policy_alert_days');
        $expired = $this->policies->filter(fn ($p) => $p->daysToExpire() < 0)->count();

        return new Content(
            view: 'emails.contratacion.policy-expiring',
            with: [
                'headline' => 'Pólizas próximas a vencer',
                'preheader' => "{$this->policies->count()} póliza(s) de {$this->project->name} vencen en los próximos {$alertDays} días.",
                'badge' => $expired ? "{$expired} vencida(s)" : "Próximos {$alertDays} días",
                'tone' => '#b91c1c',
                'toneSoft' => '#fdecec',
                'alertDays' => $alertDays,
                'ctaUrl' => config('contratacion.frontend_url') . '/app/contratacion',
                'ctaLabel' => 'Ver contratos',
            ],
        );
    }
}
