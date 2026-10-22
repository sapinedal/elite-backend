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
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pólizas próximas a vencer - {$this->project->name} ({$this->policies->count()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contratacion.policy-expiring',
            with: [
                'alertDays' => config('contratacion.policy_alert_days'),
                'appUrl' => config('contratacion.frontend_url') . '/app/contratacion',
            ],
        );
    }
}
