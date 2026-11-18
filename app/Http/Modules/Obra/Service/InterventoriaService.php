<?php

namespace App\Http\Modules\Obra\Service;

use App\Http\Modules\Obra\Models\InterventoriaReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Importa los informes semanales de interventoría desde la carpeta de Spaces:
 * busca los "... SEMANA N.pdf", lee los nuevos o modificados con el lector en Python y guarda sus datos.
 * La pantalla muestra siempre el de mayor número de semana.
 */
class InterventoriaService
{
    public const FILE_PATTERN = '/SEMANA\s*(\d+)\s*\.pdf$/iu';

    public const PHOTO_PATTERN = '/^foto-\d{2,3}\.jpg$/';

    protected const LAST_SYNC_KEY = 'interventoria_last_sync';

    protected function disk(): Filesystem
    {
        return Storage::disk(config('interventoria.disk'));
    }

    /**
     * Informes disponibles en la carpeta: [numero => ['path', 'size', 'last_modified']], ordenados por número.
     */
    public function availableFiles(): array
    {
        $files = [];
        foreach ($this->disk()->listContents(config('interventoria.folder'), false) as $item) {
            if (!$item->isFile() || !preg_match(self::FILE_PATTERN, basename($item->path()), $m)) {
                continue;
            }
            $files[(int) $m[1]] = [
                'path' => $item->path(),
                'size' => $item->fileSize(),
                'last_modified' => $item->lastModified(),
            ];
        }
        ksort($files);

        return $files;
    }

    /**
     * Importa el último informe (o todos con $all). Solo lee los que son nuevos, cambiaron o se leyeron con una
     * versión anterior del lector, salvo que se fuerce.
     *
     * @return array{imported: int[], skipped: int[], failed: array<int, string>, latest: int|null}
     */
    public function import(bool $all = false, bool $force = false): array
    {
        $files = $this->availableFiles();
        $summary = ['imported' => [], 'skipped' => [], 'failed' => [], 'latest' => $files ? max(array_keys($files)) : null];
        if (!$files) {
            return $summary;
        }

        $targets = $all ? $files : [$summary['latest'] => $files[$summary['latest']]];
        $existing = InterventoriaReport::whereIn('numero', array_keys($targets))->get()->keyBy('numero');

        foreach ($targets as $numero => $file) {
            $current = $existing->get($numero);
            $unchanged = $current
                && (int) $current->parser_version >= (int) config('interventoria.parser_version')
                && $current->file_path === $file['path']
                && (int) $current->file_size === (int) $file['size']
                && (int) $current->file_last_modified === (int) $file['last_modified'];

            if ($unchanged && !$force) {
                $summary['skipped'][] = $numero;
                continue;
            }

            try {
                $this->importFile($numero, $file);
                $summary['imported'][] = $numero;
            } catch (\Throwable $e) {
                // Se conserva el último informe bueno: la pantalla nunca queda vacía por un PDF que no se pudo leer
                Log::warning('No se pudo leer el informe de interventoría', ['numero' => $numero, 'path' => $file['path'], 'error' => $e->getMessage()]);
                $summary['failed'][$numero] = $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Descarga el PDF a un temporal, lo lee y guarda sus datos.
     */
    public function importFile(int $numero, array $file): InterventoriaReport
    {
        $tmp = tempnam(sys_get_temp_dir(), 'interventoria_');
        $photosTmp = $tmp . '_fotos';
        File::ensureDirectoryExists($photosTmp);
        try {
            $in = $this->disk()->readStream($file['path']);
            if (!$in) {
                throw new RuntimeException('No se pudo descargar el PDF.');
            }
            $out = fopen($tmp, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);

            $data = $this->parsePdf($tmp, $photosTmp);
            $this->storePhotos($numero, $photosTmp, $data['fotos'] ?? []);
        } finally {
            @unlink($tmp);
            File::deleteDirectory($photosTmp);
        }

        if (($data['numero'] ?? null) !== null && (int) $data['numero'] !== $numero) {
            Log::info('El número del informe no coincide con el nombre del archivo', ['archivo' => $numero, 'pdf' => $data['numero']]);
        }

        // Una fecha ilegible en el PDF no impide guardar el informe
        $fecha = $data['fecha'] ?? null;
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), (int) substr($fecha, 0, 4))) {
            $fecha = null;
        }

        return InterventoriaReport::updateOrCreate(['numero' => $numero], [
            'fecha' => $fecha,
            'file_path' => $file['path'],
            'file_size' => $file['size'],
            'file_last_modified' => $file['last_modified'],
            'data' => $data,
            'parser_version' => (int) ($data['parser_version'] ?? 1),
            'imported_at' => now(),
        ]);
    }

    /**
     * Ejecuta el lector en Python y devuelve el JSON del informe.
     */
    public function parsePdf(string $pdfPath, ?string $photosFolder = null): array
    {
        $command = [config('interventoria.python'), config('interventoria.parser'), $pdfPath];
        if ($photosFolder) {
            array_push($command, '--fotos', $photosFolder);
        }
        $result = Process::timeout(config('interventoria.timeout'))->run($command);

        $data = json_decode(trim($result->output()), true);
        if (!is_array($data)) {
            throw new RuntimeException('El lector de PDF no respondió correctamente: ' . trim($result->errorOutput() ?: $result->output()));
        }
        if (isset($data['error'])) {
            throw new RuntimeException('El lector de PDF falló: ' . $data['error']);
        }
        if (empty($data['numero'])) {
            throw new RuntimeException('El PDF no tiene el formato de informe semanal de interventoría.');
        }

        return $data;
    }

    /**
     * Reemplaza el registro fotográfico guardado de la semana por el recién extraído.
     */
    protected function storePhotos(int $numero, string $folder, array $photos): void
    {
        $disk = Storage::disk(config('interventoria.photos_disk'));
        $target = config('interventoria.photos_folder') . '/' . $numero;
        $disk->deleteDirectory($target);
        foreach ($photos as $photo) {
            $name = $photo['archivo'] ?? '';
            $source = $folder . '/' . $name;
            if (preg_match(self::PHOTO_PATTERN, $name) && is_file($source)) {
                $disk->put($target . '/' . $name, file_get_contents($source));
            }
        }
    }

    /**
     * Ruta de una foto guardada (null si no existe o el nombre no es válido).
     */
    public function photoPath(int $numero, string $archivo): ?string
    {
        if (!preg_match(self::PHOTO_PATTERN, $archivo)) {
            return null;
        }
        $path = config('interventoria.photos_folder') . '/' . $numero . '/' . $archivo;

        return Storage::disk(config('interventoria.photos_disk'))->exists($path) ? $path : null;
    }

    /**
     * Botón "Sincronizar": trae la carpeta desde Drive (si está configurado) e importa el último informe.
     */
    public function sync(): array
    {
        $drive = null;
        if ($command = config('interventoria.sync_command')) {
            $result = Process::timeout(config('interventoria.sync_timeout'))->run($command);
            $drive = $result->successful();
            if (!$drive) {
                Log::warning('Falló la copia de informes de interventoría desde Drive', ['error' => trim($result->errorOutput() ?: $result->output())]);
            }
        }

        $summary = $this->import();
        Cache::forever(self::LAST_SYNC_KEY, now()->toIso8601String());

        return $summary + ['drive' => $drive];
    }

    public function lastSync(): ?string
    {
        return Cache::get(self::LAST_SYNC_KEY);
    }

    /**
     * Informe a mostrar (el último o uno puntual) con la serie histórica para la gráfica.
     */
    public function dashboard(?int $numero = null): array
    {
        $report = $numero
            ? InterventoriaReport::where('numero', $numero)->first()
            : InterventoriaReport::orderByDesc('numero')->first();

        $history = InterventoriaReport::orderBy('numero')->get(['numero', 'fecha', 'data'])
            ->map(fn (InterventoriaReport $r) => [
                'numero' => $r->numero,
                'fecha' => $r->fecha?->toDateString(),
                'avance_inversion' => $r->data['presupuesto']['avance_inversion'] ?? null,
                'inversion_ejecutada' => $r->data['presupuesto']['inversion_ejecutada'] ?? null,
            ])
            ->values()
            ->all();

        if (!$report) {
            return ['report' => null, 'history' => $history, 'sync' => ['ultima' => $this->lastSync(), 'desde_drive' => (bool) config('interventoria.sync_command')]];
        }

        $pdfUrl = null;
        try {
            $pdfUrl = $this->disk()->temporaryUrl($report->file_path, now()->addMinutes(30));
        } catch (\Throwable) {
        }

        $data = $report->data;
        $data['fotos'] = collect($data['fotos'] ?? [])
            ->filter(fn ($photo) => is_array($photo) && $this->photoPath($report->numero, $photo['archivo'] ?? ''))
            ->map(fn (array $photo) => $photo + [
                'url' => URL::temporarySignedRoute('obra.interventoria.foto', now()->addHours(4), [
                    'numero' => $report->numero,
                    'archivo' => $photo['archivo'],
                ]),
            ])
            ->values()
            ->all();

        return [
            'sync' => [
                'ultima' => $this->lastSync() ?? $report->imported_at?->toIso8601String(),
                'desde_drive' => (bool) config('interventoria.sync_command'),
            ],
            'report' => [
                'numero' => $report->numero,
                'fecha' => $report->fecha?->toDateString(),
                'archivo' => basename($report->file_path),
                'ruta' => $report->file_path,
                'pdf_url' => $pdfUrl,
                'importado' => $report->imported_at?->toIso8601String(),
                'es_ultimo' => $report->numero === InterventoriaReport::max('numero'),
                ...$data,
            ],
            'history' => $history,
        ];
    }
}
