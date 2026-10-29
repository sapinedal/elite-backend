<?php

namespace App\Services;

use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Carga directa de archivos grandes a S3 (DigitalOcean Spaces), compartida por Cortes y Órdenes.
 *
 * El navegador sube el archivo DIRECTO al bucket con una URL firmada, sin pasar por PHP
 * (evita límites de upload_max_filesize, timeouts y doble transferencia):
 *
 *   1. requestUpload(): valida nombre/tamaño y firma una URL hacia una carpeta temporal.
 *   2. El navegador hace PUT del archivo a esa URL.
 *   3. claimUpload(): al guardar el registro, verifica que el token sea del usuario y MUEVE el archivo
 *      a su carpeta definitiva (ej. la carpeta del contrato), que en el paso 1 aún no se conoce.
 *
 * Requiere que el bucket tenga CORS habilitado para PUT desde el dominio del frontend.
 */
class DirectUploadService
{
    protected const CACHE_PREFIX = 'direct_upload:';

    // Carpeta del bucket donde quedan los archivos mientras se guarda el registro
    public const TMP_ROOT = 'tmp/cargas-directas';

    public function __construct(
        protected DocumentalService $documental,
        protected DocumentalSettingsService $settings,
    ) {
    }

    protected function disk(): Filesystem
    {
        return $this->documental->getDisk();
    }

    /**
     * Prepara la subida directa y devuelve la URL firmada con el token para guardar el registro.
     *
     * @return array{upload_token: string, upload_url: string, headers: array<string, string>}
     */
    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user, int $maxMb, int $minutes = 120): array
    {
        if ($fileSize <= 0) {
            throw new InvalidArgumentException("El archivo \"{$fileName}\" está vacío.");
        }
        if ($fileSize > $maxMb * 1024 * 1024) {
            throw new InvalidArgumentException("El archivo \"{$fileName}\" supera el tamaño máximo permitido de {$maxMb} MB.");
        }
        // Se respetan las extensiones bloqueadas del repositorio documental (ej. .exe, .bat)
        if ($reason = $this->settings->validateExtension($fileName)) {
            throw new InvalidArgumentException($reason);
        }

        $token = (string) Str::uuid();
        $cleanName = $this->cleanSegment($fileName);
        $path = self::TMP_ROOT . "/{$token}/{$cleanName}";
        $contentType = $mime ?: 'application/octet-stream';

        $signed = $this->disk()->temporaryUploadUrl($path, now()->addMinutes($minutes), ['ContentType' => $contentType]);

        Cache::put(self::CACHE_PREFIX . $token, [
            'user_id' => $user->id,
            'path' => $path,
            'name' => $cleanName,
            'mime' => $contentType,
            'max_mb' => $maxMb,
        ], now()->addMinutes($minutes + 60));

        return [
            'upload_token' => $token,
            'upload_url' => $signed['url'],
            // El navegador debe enviar el mismo Content-Type con el que se firmó la URL
            'headers' => ['Content-Type' => $contentType],
        ];
    }

    /**
     * Valida un archivo subido con requestUpload(), lo mueve a $directory y devuelve sus datos.
     * El token se consume: no se puede reutilizar el mismo archivo en dos registros.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimUpload(string $token, User $user, string $directory): array
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
        if ($size > $upload['max_mb'] * 1024 * 1024) {
            $disk->delete($upload['path']);
            Cache::forget(self::CACHE_PREFIX . $token);
            throw new InvalidArgumentException("El archivo supera el tamaño máximo permitido de {$upload['max_mb']} MB.");
        }

        // Copia dentro del bucket (no pasa por el servidor) a la carpeta definitiva, sin pisar archivos existentes
        $directory = trim($directory, '/');
        $name = $this->uniqueFilename($directory, $upload['name']);
        $finalPath = "{$directory}/{$name}";
        $disk->move($upload['path'], $finalPath);

        Cache::forget(self::CACHE_PREFIX . $token);
        $this->documental->clearPathCache($directory);

        return [
            'file_name' => $name,
            'file_path' => $finalPath,
            'file_size' => $size,
            'file_mime' => $upload['mime'],
        ];
    }

    /**
     * Mueve un archivo ya guardado a otra carpeta (reorganización) y devuelve la nueva ruta.
     */
    public function moveTo(string $path, string $directory): string
    {
        $directory = trim($directory, '/');
        $finalPath = $directory . '/' . $this->uniqueFilename($directory, basename($path));
        $this->disk()->move($path, $finalPath);
        $this->documental->clearPathCache($directory);

        return $finalPath;
    }

    /**
     * Elimina un archivo ya guardado (ej. una factura cargada por error).
     */
    public function delete(string $path): void
    {
        $this->disk()->delete($path);
        $this->documental->clearPathCache(dirname($path));
    }

    /**
     * URL temporal (60 min) para descargar un archivo con su nombre original.
     */
    public function downloadUrl(string $path, string $name): string
    {
        $disk = $this->disk();
        if (!$disk->exists($path)) {
            throw new InvalidArgumentException('El archivo ya no existe en el repositorio.');
        }

        return $disk->temporaryUrl($path, now()->addMinutes(60), [
            'ResponseContentDisposition' => "attachment; filename*=UTF-8''" . rawurlencode($name),
        ]);
    }

    /**
     * Limpia un nombre de archivo o carpeta de caracteres problemáticos en S3 / URLs.
     */
    public function cleanSegment(string $value, int $limit = 150): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', str_replace(['/', '\\', '..', '#', '?', '%', '&'], '-', $value)));
        $clean = ltrim($clean, '.');

        if ($clean === '') {
            throw new InvalidArgumentException('El nombre del archivo no es válido.');
        }

        return trim(Str::limit($clean, $limit, ''));
    }

    protected function uniqueFilename(string $directory, string $filename): string
    {
        $disk = $this->disk();
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';

        $candidate = $filename;
        $counter = 1;
        while ($disk->exists("{$directory}/{$candidate}")) {
            $candidate = "{$base} ({$counter}){$suffix}";
            $counter++;
        }

        return $candidate;
    }
}
