<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Documental\Models\DocumentShareLink;
use App\Http\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enlaces públicos permanentes: la URL compartida apunta a la app (no a Spaces), por eso no caduca.
 * En cada acceso se valida el enlace y se redirige a una URL firmada corta de Spaces.
 */
class DocumentShareLinkService
{
    /** Vigencia de la URL firmada a la que se redirige en cada acceso (no la del enlace, que es permanente) */
    protected const REDIRECT_MINUTES = 10;

    public function __construct(protected DocumentalService $documental)
    {
    }

    /**
     * Devuelve el enlace permanente del archivo (reutiliza el activo si ya existe).
     */
    public function createLink(string $filePath, User $user, ?string $ip = null): array
    {
        $path = $this->assertReadableFile($filePath, $user, $ip);

        $link = DocumentShareLink::active()->where('path', $path)->first();
        if (!$link) {
            $link = DocumentShareLink::create([
                'token' => Str::random(48),
                'path' => $path,
                'created_by' => $user->id,
            ]);
            $this->documental->log($user, 'share_link', $path, ['filename' => basename($path)], $ip);
        }

        return $this->present($link);
    }

    /**
     * Revoca el enlace permanente del archivo: la URL deja de funcionar para todos.
     */
    public function revokeLink(string $filePath, User $user, ?string $ip = null): array
    {
        $path = $this->assertReadableFile($filePath, $user, $ip, false);

        $revoked = DocumentShareLink::active()->where('path', $path)->update(['revoked_at' => now()]);
        if ($revoked === 0) {
            throw new InvalidArgumentException('Este archivo no tiene un enlace público activo.');
        }
        $this->documental->log($user, 'revoke_share_link', $path, ['filename' => basename($path)], $ip);

        return ['message' => 'Enlace público revocado. La URL anterior ya no funciona.', 'path' => $path];
    }

    /**
     * URL firmada corta para servir el archivo de un enlace público. Null si el enlace no existe,
     * fue revocado o el archivo ya no está.
     */
    public function resolve(string $token): ?string
    {
        $link = DocumentShareLink::active()->where('token', $token)->first();
        if (!$link) {
            return null;
        }

        $disk = $this->documental->getDisk();
        if (!$disk->exists($link->path)) {
            return null;
        }

        $link->increment('access_count', 1, ['last_accessed_at' => now()]);

        $filename = basename($link->path);

        return $disk->temporaryUrl($link->path, now()->addMinutes(self::REDIRECT_MINUTES), [
            'ResponseContentDisposition' => "inline; filename*=UTF-8''" . rawurlencode($filename),
        ]);
    }

    protected function assertReadableFile(string $filePath, User $user, ?string $ip, bool $mustExist = true): string
    {
        $path = $this->documental->normalizePath($filePath);
        if ($path === '') {
            throw new InvalidArgumentException('Indica el archivo a compartir.');
        }
        if (!$this->documental->getUserPermissionsForPath($user, $path)['can_read']) {
            if (config('documental.log_denied_attempts')) {
                $this->documental->log($user, 'denied', $path, ['attempted_action' => 'share_link', 'reason' => 'Sin permiso de lectura'], $ip);
            }
            throw new AuthorizationException('No tienes permisos para compartir este archivo.');
        }
        if ($mustExist && !$this->documental->getDisk()->exists($path)) {
            throw new InvalidArgumentException('El archivo especificado no existe.');
        }

        return $path;
    }

    protected function present(DocumentShareLink $link): array
    {
        return [
            'url' => route('documental.share', ['token' => $link->token]),
            'path' => $link->path,
            'created_at' => $link->created_at?->toIso8601String(),
            'access_count' => $link->access_count,
        ];
    }
}
