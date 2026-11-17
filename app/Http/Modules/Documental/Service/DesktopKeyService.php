<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Documental\Models\DocumentDesktopKey;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Claves de escritorio para conectar Gestión Documental como unidad de red.
 * Son distintas de la contraseña de Elite, se pueden revocar una por una y solo se guarda su hash.
 */
class DesktopKeyService
{
    public const MAX_KEYS_PER_USER = 10;

    /** Prefijo de las claves creadas automáticamente al iniciar sesión desde Office (Word, Excel…) */
    public const OFFICE_KEY_PREFIX = 'Office en Windows';

    public function list(User $user): array
    {
        return DocumentDesktopKey::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (DocumentDesktopKey $key) => $this->present($key))
            ->all();
    }

    /**
     * Crea una clave y devuelve el valor en claro (única vez que se puede ver).
     */
    public function create(User $user, string $name): array
    {
        if (DocumentDesktopKey::where('user_id', $user->id)->count() >= self::MAX_KEYS_PER_USER) {
            throw new InvalidArgumentException('Alcanzaste el máximo de ' . self::MAX_KEYS_PER_USER . ' claves. Revoca las que ya no uses.');
        }

        $plain = $this->generatePlain();

        $key = DocumentDesktopKey::create([
            'user_id' => $user->id,
            'name' => trim($name),
            'token_hash' => $this->hash($plain),
            'last_four' => substr($plain, -4),
        ]);

        return $this->present($key) + ['key' => $plain];
    }

    /**
     * Sesión de Office: al iniciar sesión en la ventana que abre Word o Excel se crea una clave propia
     * (visible y revocable en "Conectar al escritorio") que viaja cifrada en una cookie.
     * Si el usuario ya llegó al máximo se reemplaza su sesión de Office más antigua.
     */
    public function createOfficeSession(User $user, ?string $ip = null): string
    {
        if (DocumentDesktopKey::where('user_id', $user->id)->count() >= self::MAX_KEYS_PER_USER) {
            $oldest = DocumentDesktopKey::where('user_id', $user->id)
                ->where('name', 'like', self::OFFICE_KEY_PREFIX . '%')
                ->orderByRaw('coalesce(last_used_at, created_at)')
                ->first();
            if (!$oldest) {
                throw new InvalidArgumentException('Alcanzaste el máximo de ' . self::MAX_KEYS_PER_USER . ' claves de escritorio. Revoca alguna desde Elite → Gestión Documental → Conectar al escritorio.');
            }
            $oldest->delete();
        }

        $plain = $this->generatePlain();
        DocumentDesktopKey::create([
            'user_id' => $user->id,
            'name' => self::OFFICE_KEY_PREFIX . ($ip ? " · {$ip}" : ''),
            'token_hash' => $this->hash($plain),
            'last_four' => substr($plain, -4),
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);

        return $plain;
    }

    /**
     * Valida solo la clave (sesión de Office guardada en cookie): el usuario sale de la propia clave.
     */
    public function authenticateToken(string $plain, ?string $ip = null): ?User
    {
        $key = DocumentDesktopKey::where('token_hash', $this->hash(Str::upper(trim($plain))))->first();
        if (!$key) {
            return null;
        }

        return $this->authenticate(User::whereKey($key->user_id)->value('email') ?? '', $plain, $ip);
    }

    /**
     * Comprueba si la clave pertenece al usuario, sin registrar uso (para el formulario de inicio de sesión).
     */
    public function belongsTo(User $user, string $plain): bool
    {
        return DocumentDesktopKey::where('user_id', $user->id)
            ->where('token_hash', $this->hash(Str::upper(trim($plain))))
            ->exists();
    }

    /**
     * Sin caracteres ambiguos para poder dictarla o copiarla a mano si hace falta.
     */
    protected function generatePlain(): string
    {
        $plain = collect(range(1, 4))->map(fn () => Str::upper(Str::random(5)))->join('-');

        return strtr($plain, ['0' => '8', 'O' => 'P', 'I' => 'J', '1' => '7', 'L' => 'M']);
    }

    public function revoke(User $user, int $keyId): void
    {
        $deleted = DocumentDesktopKey::where('user_id', $user->id)->whereKey($keyId)->delete();
        if ($deleted === 0) {
            throw new InvalidArgumentException('La clave no existe o ya fue revocada.');
        }
    }

    /**
     * Valida usuario (correo) y clave de escritorio. Devuelve el usuario si es válida, activo y con acceso al módulo.
     */
    public function authenticate(string $email, string $plain, ?string $ip = null): ?User
    {
        $email = trim($email);
        $plain = Str::upper(trim($plain));
        if ($email === '' || $plain === '') {
            return null;
        }

        $key = DocumentDesktopKey::where('token_hash', $this->hash($plain))->first();
        if (!$key) {
            return null;
        }

        // User excluye los eliminados (inactivos) por SoftDeletes
        $user = User::whereKey($key->user_id)->first();
        if (!$user || strcasecmp($user->email, $email) !== 0 || !$user->can(DocumentalService::PERMISSION_VIEW)) {
            return null;
        }

        // Evita escribir en cada petición: los exploradores hacen decenas por minuto
        if (!$key->last_used_at || $key->last_used_at->lt(now()->subMinutes(5)) || $key->last_used_ip !== $ip) {
            $key->forceFill(['last_used_at' => now(), 'last_used_ip' => $ip])->saveQuietly();
        }

        return $user;
    }

    protected function hash(string $plain): string
    {
        // La clave es aleatoria (~100 bits), así que un SHA-256 simple basta y no depende de APP_KEY
        return hash('sha256', $plain);
    }

    protected function present(DocumentDesktopKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'last_four' => $key->last_four,
            'created_at' => $key->created_at?->toIso8601String(),
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'last_used_ip' => $key->last_used_ip,
        ];
    }
}
