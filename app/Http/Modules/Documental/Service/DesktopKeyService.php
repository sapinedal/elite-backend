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

        // Sin caracteres ambiguos para poder dictarla o copiarla a mano si hace falta
        $plain = collect(range(1, 4))->map(fn () => Str::upper(Str::random(5)))->join('-');
        $plain = strtr($plain, ['0' => '8', 'O' => 'P', 'I' => 'J', '1' => '7', 'L' => 'M']);

        $key = DocumentDesktopKey::create([
            'user_id' => $user->id,
            'name' => trim($name),
            'token_hash' => $this->hash($plain),
            'last_four' => substr($plain, -4),
        ]);

        return $this->present($key) + ['key' => $plain];
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
