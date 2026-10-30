<?php

namespace App\Http\Modules\Auth\Service;

use App\Http\Modules\Auth\Mail\PasswordResetMail;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Restablecer la contraseña por correo con el broker de Laravel (tabla password_reset_tokens):
 *
 *   1. sendLink(): si el correo es de un usuario activo, le envía un enlace al frontend con un token
 *      de un solo uso (vence en auth.passwords.users.expire minutos; uno por minuto como máximo).
 *   2. reset(): con el token válido guarda la nueva contraseña y cierra las sesiones abiertas.
 *
 * La respuesta del paso 1 es la misma exista o no el correo, para no revelar qué usuarios tiene la plataforma.
 */
class PasswordResetService
{
    public function sendLink(string $email): void
    {
        $status = Password::broker()->sendResetLink(
            ['email' => $this->registeredEmail($email)],
            function (User $user, string $token) {
                try {
                    Mail::to($user->email)->send(new PasswordResetMail($user, $this->resetUrl($user, $token)));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Log::info("Restablecer contraseña: no se envió enlace a {$email} ({$status}).");
        }
    }

    /**
     * @throws InvalidArgumentException si el enlace no es válido o venció
     */
    public function reset(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => $this->registeredEmail($email), 'token' => $token, 'password' => $password],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                // Quien tenía la sesión abierta (ej. si alguien más conocía la contraseña) debe volver a ingresar
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidArgumentException('El enlace para restablecer la contraseña no es válido o ya venció. Solicita uno nuevo.');
        }
    }

    /**
     * El correo como está registrado (sin distinguir mayúsculas), porque el broker lo busca exacto.
     */
    protected function registeredEmail(string $email): string
    {
        $email = trim($email);

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->value('email') ?? $email;
    }

    protected function resetUrl(User $user, string $token): string
    {
        return config('app.frontend_url') . '/restablecer-clave?' . http_build_query(['token' => $token, 'email' => $user->email]);
    }
}
