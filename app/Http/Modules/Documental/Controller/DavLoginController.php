<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Documental\Service\DesktopKeyService;
use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Inicio de sesión de Office para la unidad de red (protocolo MS-OFBA).
 * Word y Excel bloquean el inicio de sesión Basic, así que cuando abren un archivo de la unidad el servidor les
 * indica esta página: Office la muestra en una ventana, el usuario entra con su correo y su contraseña de Elite
 * (o una clave de escritorio) y queda una cookie cifrada que Office envía en las siguientes peticiones.
 */
class DavLoginController extends Controller
{
    public const COOKIE = 'elite_dav_session';

    public const COOKIE_DAYS = 30;

    protected const MAX_ATTEMPTS = 5;

    public function __construct(
        protected DesktopKeyService $keys,
        protected DocumentalService $documental,
    ) {
    }

    public static function loginUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/dav-login';
    }

    public static function returnUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/dav-login/listo';
    }

    public function show(Request $request)
    {
        // Si la ventana ya tiene una sesión vigente (Excel se reinició y olvidó la suya), se renueva y se cierra
        // al instante: Office toma la cookie al llegar a la página de retorno, sin pedir la contraseña otra vez
        $user = $this->userFromCookie($request);
        $token = $user ? $this->tokenFromCookie($request) : null;
        if ($user && $token) {
            return $this->completeLogin($request, $token);
        }

        return response()->view('documental.dav-login', ['error' => null, 'email' => '']);
    }

    public function login(Request $request)
    {
        $email = trim((string) $request->input('email'));
        $secret = (string) $request->input('password');
        $throttleKey = 'documental-dav-login:' . Str::lower($email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            return $this->fail($email, "Demasiados intentos fallidos. Intenta de nuevo en {$minutes} minuto(s).");
        }

        $user = $email !== '' ? User::whereRaw('lower(email) = ?', [Str::lower($email)])->first() : null;
        $usedDesktopKey = $user && $secret !== '' && $this->keys->belongsTo($user, $secret);
        $validPassword = $user && $secret !== '' && !$usedDesktopKey && Hash::check($secret, $user->password);

        if (!$user || (!$usedDesktopKey && !$validPassword)) {
            RateLimiter::hit($throttleKey, 300);

            return $this->fail($email, 'Correo o contraseña incorrectos.');
        }

        if (!$user->can(DocumentalService::PERMISSION_VIEW)) {
            return $this->fail($email, 'Tu usuario no tiene acceso a Gestión Documental.');
        }

        RateLimiter::clear($throttleKey);

        try {
            // Con una clave de escritorio se reutiliza; con la contraseña se crea una sesión de Office revocable
            $token = $usedDesktopKey ? Str::upper(trim($secret)) : $this->keys->createOfficeSession($user, $request->ip());
        } catch (InvalidArgumentException $e) {
            return $this->fail($email, $e->getMessage());
        }

        $this->documental->log($user, 'desktop_login', '', [
            'source' => 'escritorio',
            'client' => Str::limit((string) $request->userAgent(), 120, ''),
            'method' => $usedDesktopKey ? 'clave de escritorio' : 'contraseña',
        ], $request->ip());

        return $this->completeLogin($request, $token);
    }

    /**
     * Redirige a la página de retorno (señal para que Office cierre la ventana) con la cookie de sesión.
     * Es persistente: Office la comparte entre la ventana de inicio de sesión y sus peticiones a la unidad.
     */
    protected function completeLogin(Request $request, string $token): RedirectResponse
    {
        return redirect()->away(self::returnUrl(), 302)->withCookie(new Cookie(
            self::COOKIE,
            Crypt::encryptString($token),
            now()->addDays(self::COOKIE_DAYS),
            '/api',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));
    }

    public function done()
    {
        return response()->view('documental.dav-login', ['done' => true, 'error' => null, 'email' => '']);
    }

    /**
     * Usuario de la cookie de sesión de Office, si es válida (clave vigente, usuario activo y con acceso).
     */
    public function userFromCookie(Request $request): ?User
    {
        $token = $this->tokenFromCookie($request);

        return $token ? $this->keys->authenticateToken($token, $request->ip()) : null;
    }

    protected function tokenFromCookie(Request $request): ?string
    {
        $value = $request->cookies->get(self::COOKIE);
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function fail(string $email, string $message)
    {
        return response()->view('documental.dav-login', ['error' => $message, 'email' => $email], 422);
    }
}
