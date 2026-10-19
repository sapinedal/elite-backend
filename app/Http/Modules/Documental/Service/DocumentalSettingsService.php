<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Models\DocumentSetting;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Parámetros del módulo documental administrables desde la aplicación.
 */
class DocumentalSettingsService
{
    public const MODE_BLOCKLIST = 'blocklist';
    public const MODE_ALLOWLIST = 'allowlist';

    public const DEFAULTS = [
        'max_upload_mb' => 100,
        // blocklist: se permite todo excepto las bloqueadas; allowlist: solo se permiten las listadas
        'extension_mode' => self::MODE_BLOCKLIST,
        'blocked_extensions' => ['exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll', 'ps1', 'vbs', 'js', 'jar', 'sh', 'php'],
        'allowed_extensions' => [],
        // Renombrar o eliminar una carpeta procesa cada archivo; por encima de este límite la operación
        // tardaría demasiado para una petición web y podría quedar a medias
        'max_folder_operation_files' => 1000,
    ];

    protected const CACHE_KEY = 'documental_settings';

    protected ?array $settings = null;

    protected function cache()
    {
        return Cache::store(config('documental.cache_store'));
    }

    /**
     * Configuración vigente (valores guardados sobre los valores por defecto).
     */
    public function all(): array
    {
        return $this->settings ??= array_merge(
            self::DEFAULTS,
            $this->cache()->rememberForever(
                self::CACHE_KEY,
                fn () => DocumentSetting::pluck('value', 'key')->only(array_keys(self::DEFAULTS))->all()
            )
        );
    }

    /**
     * Configuración más los límites del servidor, para mostrar en la interfaz.
     */
    public function payload(): array
    {
        $settings = $this->all();
        $serverMax = $this->serverMaxUploadMb();

        return $settings + [
            'server_max_upload_mb' => $serverMax,
            'effective_max_upload_mb' => min($settings['max_upload_mb'], $serverMax),
        ];
    }

    public function update(array $data, User $user, ?string $ip = null): array
    {
        $before = $this->all();

        $values = [
            'max_upload_mb' => (int) $data['max_upload_mb'],
            'extension_mode' => $data['extension_mode'],
            'blocked_extensions' => $this->normalizeExtensions($data['blocked_extensions'] ?? []),
            'allowed_extensions' => $this->normalizeExtensions($data['allowed_extensions'] ?? []),
            'max_folder_operation_files' => (int) ($data['max_folder_operation_files'] ?? self::DEFAULTS['max_folder_operation_files']),
        ];

        DB::transaction(function () use ($values, $user) {
            foreach ($values as $key => $value) {
                DocumentSetting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $user->id]);
            }
        });

        $this->cache()->forget(self::CACHE_KEY);
        $this->settings = null;

        $changes = [];
        foreach ($values as $key => $value) {
            if ($before[$key] !== $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }

        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => 'settings',
            'path' => '',
            'details' => ['changes' => $changes],
            'ip_address' => $ip,
        ]);

        return $this->payload();
    }

    /**
     * Devuelve el motivo por el que un archivo no se puede subir, o null si es válido.
     */
    public function validateUpload(string $filename, int $sizeBytes): ?string
    {
        $maxMb = $this->payload()['effective_max_upload_mb'];
        if ($sizeBytes > $maxMb * 1024 * 1024) {
            return "El archivo \"{$filename}\" supera el tamaño máximo permitido de {$maxMb} MB.";
        }

        return $this->validateExtension($filename);
    }

    /**
     * Devuelve el motivo por el que la extensión del archivo no está permitida, o null si es válida.
     */
    public function validateExtension(string $filename): ?string
    {
        $settings = $this->all();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($settings['extension_mode'] === self::MODE_ALLOWLIST) {
            if ($extension === '' || !in_array($extension, $settings['allowed_extensions'], true)) {
                $label = $extension === '' ? 'sin extensión' : ".{$extension}";

                return "No se permiten archivos {$label} (\"{$filename}\"). Extensiones permitidas: "
                    . implode(', ', $settings['allowed_extensions']) . '.';
            }

            return null;
        }

        if ($extension !== '' && in_array($extension, $settings['blocked_extensions'], true)) {
            return "Los archivos .{$extension} están bloqueados en el repositorio (\"{$filename}\").";
        }

        return null;
    }

    /**
     * Límite real de PHP para subidas: el menor entre upload_max_filesize y post_max_size.
     */
    public function serverMaxUploadMb(): int
    {
        $limits = array_filter([
            $this->iniToBytes((string) ini_get('upload_max_filesize')),
            $this->iniToBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? max(1, (int) floor(min($limits) / 1024 / 1024)) : self::DEFAULTS['max_upload_mb'];
    }

    protected function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0; // 0 = sin límite
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    protected function normalizeExtensions(array $extensions): array
    {
        return collect($extensions)
            ->map(fn ($ext) => strtolower(trim(ltrim((string) $ext, '.'))))
            ->filter(fn (string $ext) => preg_match('/^[a-z0-9]{1,15}$/', $ext))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
