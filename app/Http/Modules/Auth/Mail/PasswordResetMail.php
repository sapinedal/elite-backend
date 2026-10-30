<?php

namespace App\Http\Modules\Auth\Mail;

use App\Http\Modules\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Enlace para restablecer la contraseña (vence en auth.passwords.users.expire minutos).
 * Plantilla: emails/auth/password-reset sobre el layout de marca.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Restablece tu contraseña de Elite',
        );
    }

    public function content(): Content
    {
        $minutes = (int) config('auth.passwords.users.expire');

        return new Content(
            view: 'emails.auth.password-reset',
            with: [
                'headline' => 'Restablece tu contraseña',
                'preheader' => "Usa este enlace para crear una nueva contraseña. Vence en {$minutes} minutos.",
                'badge' => 'Seguridad de la cuenta',
                'tone' => '#004C6C',
                'toneSoft' => '#e6f0f5',
                'ctaUrl' => $this->url,
                'ctaLabel' => 'Crear nueva contraseña',
                'name' => $this->user->first_name ?: $this->user->name,
                'minutes' => $minutes,
            ],
        );
    }
}
