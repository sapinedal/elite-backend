<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Archivos de los cortes en S3 (DigitalOcean Spaces).
 *
 * Los cortes pueden pesar hasta 1 GB, así que el navegador sube el archivo DIRECTO al bucket con una
 * URL firmada en lugar de pasar por PHP (evita límites de upload_max_filesize, timeouts y doble transferencia):
 *
 *   1. requestUpload(): valida nombre/tamaño, genera la ruta y la URL firmada, y guarda un token en caché.
 *   2. El navegador hace PUT del archivo a esa URL.
 *   3. claimUpload(): al radicar, verifica que el token sea del usuario y que el archivo exista y no supere el límite.
 *
 * Requiere que el bucket tenga CORS habilitado para PUT desde el dominio del frontend.
 */
class CorteStorageService
{
    protected const CACHE_PREFIX = 'cortes_upload:';

    public function __construct(protected DocumentalSettingsService $settings)
    {
    }

    protected function disk(): Filesystem
    {
        return Storage::disk('s3');
    }

    public function maxBytes(): int
    {
        return config('cortes.max_upload_mb') * 1024 * 1024;
    }

    /**
     * Prepara la subida directa a S3 y devuelve la URL firmada con el token para radicar.
     *
     * @return array{upload_token: string, upload_url: string, headers: array<string, string>}
     */
    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        if ($fileSize <= 0) {
            throw new InvalidArgumentException("El archivo \"{$fileName}\" está vacío.");
        }
        if ($fileSize > $this->maxBytes()) {
            $maxMb = config('cortes.max_upload_mb');
            throw new InvalidArgumentException("El archivo \"{$fileName}\" supera el tamaño máximo permitido de {$maxMb} MB.");
        }
        // Se respetan las extensiones bloqueadas del repositorio documental (ej. .exe, .bat)
        if ($reason = $this->settings->validateExtension($fileName)) {
            throw new InvalidArgumentException($reason);
        }

        $token = (string) Str::uuid();
        $cleanName = $this->cleanFileName($fileName);
        $path = trim(config('cortes.s3_root'), '/') . '/' . now()->format('Y/m') . "/{$token}/{$cleanName}";
        $contentType = $mime ?: 'application/octet-stream';
        $minutes = config('cortes.upload_url_minutes');

        $signed = $this->disk()->temporaryUploadUrl($path, now()->addMinutes($minutes), ['ContentType' => $contentType]);

        Cache::put(self::CACHE_PREFIX . $token, [
            'user_id' => $user->id,
            'path' => $path,
            'name' => $cleanName,
            'mime' => $contentType,
        ], now()->addMinutes($minutes + 60));

        return [
            'upload_token' => $token,
            'upload_url' => $signed['url'],
            // El navegador debe enviar el mismo Content-Type con el que se firmó la URL
            'headers' => ['Content-Type' => $contentType],
        ];
    }

    /**
     * Valida un archivo ya subido con requestUpload() y devuelve sus datos para guardarlos en el corte.
     * El token se consume: no se puede reutilizar el mismo archivo en dos cortes.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimUpload(string $token, User $user): array
    {
        $upload = Cache::get(self::CACHE_PREFIX . $token);
        if (!$upload || $upload['user_id'] !== $user->id) {
            throw new InvalidArgumentException('La carga del archivo expiró o no es válida. Vuelve a seleccionar el archivo.');
        }

        $disk = $this->disk();
        if (!$disk->exists($upload['path'])) {
            throw new InvalidArgumentException('El archivo no terminó de subirse. Vuelve a intentarlo.');
        }

        // El tamaño se verifica contra lo que realmente quedó en S3, no contra lo que dijo el navegador
        $size = (int) $disk->size($upload['path']);
        if ($size > $this->maxBytes()) {
            $disk->delete($upload['path']);
            Cache::forget(self::CACHE_PREFIX . $token);
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido de ' . config('cortes.max_upload_mb') . ' MB.');
        }

        Cache::forget(self::CACHE_PREFIX . $token);

        return [
            'file_name' => $upload['name'],
            'file_path' => $upload['path'],
            'file_size' => $size,
            'file_mime' => $upload['mime'],
        ];
    }

    public function downloadUrl(Corte $corte): string
    {
        $disk = $this->disk();
        if (!$disk->exists($corte->file_path)) {
            throw new InvalidArgumentException('El archivo del corte ya no existe en el repositorio.');
        }

        return $disk->temporaryUrl($corte->file_path, now()->addMinutes(60), [
            'ResponseContentDisposition' => "attachment; filename*=UTF-8''" . rawurlencode($corte->file_name),
        ]);
    }

    /**
     * Limpia el nombre del archivo de caracteres problemáticos en S3 / URLs.
     */
    protected function cleanFileName(string $value): string
    {
        $clean = trim(str_replace(['/', '\\', '..', '#', '?', '%', '&'], '-', $value));
        $clean = ltrim($clean, '.');

        if ($clean === '') {
            throw new InvalidArgumentException('El nombre del archivo no es válido.');
        }

        return Str::limit($clean, 150, '');
    }
}
