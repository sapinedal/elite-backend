<?php

namespace App\Http\Modules\Documental\Dav;

use Illuminate\Support\Facades\Cache;
use Sabre\DAV\File;

/**
 * Archivo de Gestión Documental en la unidad de red. Se lee y escribe directo en Spaces.
 */
class DavFile extends File
{
    public function __construct(
        protected DavContext $context,
        public readonly string $path,
        protected int $size,
        protected ?int $lastModified,
    ) {
    }

    public function getName(): string
    {
        return basename($this->path);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function getContentType(): string
    {
        return $this->context->service->guessMimeType(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function getETag(): string
    {
        return '"' . md5($this->path . '|' . $this->size . '|' . $this->lastModified) . '"';
    }

    /**
     * Registra la descarga en auditoría. Los exploradores leen el mismo archivo varias veces (vista previa,
     * miniaturas, lectura por partes): queda registrada como máximo una vez cada 30 minutos por usuario y archivo.
     */
    public function logDownload(): void
    {
        $user = $this->context->user();
        $logKey = 'documental_dav_download_' . $user->id . '_' . md5($this->path);
        if (Cache::store(config('documental.cache_store'))->add($logKey, true, now()->addMinutes(30))) {
            $this->context->service->log($user, 'download', $this->path, ['filename' => $this->getName()] + DavContext::SOURCE, $this->context->ip);
        }
    }

    /**
     * URL firmada temporal de Spaces para que nginx sirva el archivo directamente.
     */
    public function temporaryUrl(): string
    {
        return $this->context->service->getDisk()->temporaryUrl($this->path, now()->addMinutes(15));
    }

    /**
     * @return resource
     */
    public function get()
    {
        $this->logDownload();

        return $this->context->service->getDisk()->readStream($this->path);
    }

    public function put($data)
    {
        $this->context->run(function () use ($data) {
            [$stream, $size] = self::bufferBody($data);
            try {
                $this->context->service->writeFileStream($this->path, $stream, $size, $this->context->user(), $this->context->ip, DavContext::SOURCE);
            } finally {
                fclose($stream);
            }
            $this->size = $size;
            $this->lastModified = time();
        });

        return $this->getETag();
    }

    public function delete(): void
    {
        $this->context->run(fn () => $this->context->service->deleteItem($this->path, 'file', $this->context->user(), $this->context->ip));
    }

    public function setName($name): void
    {
        $this->context->run(fn () => $this->context->service->renameItem($this->path, $name, 'file', $this->context->user(), $this->context->ip));
    }

    /**
     * Copia el cuerpo de la petición a un temporal (memoria hasta 8 MB, luego disco) para conocer su tamaño
     * antes de validarlo y subirlo a Spaces.
     *
     * @param resource|string|null $data
     * @return array{0: resource, 1: int}
     */
    public static function bufferBody($data): array
    {
        $buffer = fopen('php://temp/maxmemory:' . (8 * 1024 * 1024), 'w+b');
        if (is_resource($data)) {
            stream_copy_to_stream($data, $buffer);
        } elseif ($data !== null) {
            fwrite($buffer, (string) $data);
        }
        $size = ftell($buffer);
        rewind($buffer);

        return [$buffer, $size];
    }
}
