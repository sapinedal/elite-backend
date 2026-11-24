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

    // Módulo que usa el servicio (ej. "Cortes"), para la Auditoría de Actividad del gestor documental
    protected ?string $module = null;

    public function __construct(
        protected DocumentalService $documental,
        protected DocumentalSettingsService $settings,
    ) {
    }

    /**
     * Copia del servicio que registra en la Auditoría de Actividad con el nombre del módulo indicado.
     */
    public function forModule(string $module): static
    {
        $clone = clone $this;
        $clone->module = $module;

        return $clone;
    }

    /**
     * Registra en la Auditoría de Actividad del gestor documental (si el módulo se identificó con forModule()).
     */
    protected function audit(string $action, string $path, array $details = [], ?User $user = null): void
    {
        $this->documental->recordActivity($action, $path, $this->module ?? 'Otros módulos', $details, $user);
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
        $this->audit('upload', $finalPath, ['size' => $size, 'mime_type' => $upload['mime']], $user);

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
        $this->audit('move', $finalPath, ['from' => $path, 'to' => $finalPath, 'type' => 'file']);

        return $finalPath;
    }

    /**
     * Copia un archivo ya guardado a otra carpeta, sin pisar archivos existentes, y devuelve la nueva ruta.
     * La copia ocurre dentro del bucket: no pasa por el servidor.
     */
    public function copyTo(string $path, string $directory): string
    {
        $directory = trim($directory, '/');
        $finalPath = $directory . '/' . $this->uniqueFilename($directory, basename($path));
        $this->disk()->copy($path, $finalPath);
        $this->documental->clearPathCache($directory);
        $this->audit('copy', $finalPath, ['from' => $path, 'to' => $finalPath]);

        return $finalPath;
    }

    /**
     * Elimina un archivo ya guardado (ej. una factura cargada por error).
     */
    public function delete(string $path): void
    {
        $this->disk()->delete($path);
        $this->documental->clearPathCache(dirname($path));
        $this->audit('delete', $path, ['type' => 'file']);
    }

    /**
     * URL temporal (60 min) de un archivo con su nombre original.
     * Con $inline el navegador lo muestra en su visor (ej. un PDF en una pestaña) en lugar de descargarlo.
     * Sin indicarlo se toma ?inline=1 de la petición: así todos los endpoints de descarga de los módulos
     * (cortes, anticipos, facturas...) sirven también para "Ver" sin cambiar cada controlador.
     */
    public function downloadUrl(string $path, string $name, ?bool $inline = null): string
    {
        $disk = $this->disk();
        if (!$disk->exists($path)) {
            throw new InvalidArgumentException('El archivo ya no existe en el repositorio.');
        }

        return $disk->temporaryUrl($path, now()->addMinutes(60), self::responseOptions($name, $inline ?? request()->boolean('inline')));
    }

    /**
     * Cabeceras de la respuesta de S3: descarga (attachment) o visualización en el navegador (inline).
     * Para ver un PDF se fuerza su Content-Type: archivos antiguos pueden tener uno genérico en el bucket.
     */
    public static function responseOptions(string $name, bool $inline): array
    {
        $options = ['ResponseContentDisposition' => ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($name)];
        if ($inline && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf') {
            $options['ResponseContentType'] = 'application/pdf';
        }

        return $options;
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
