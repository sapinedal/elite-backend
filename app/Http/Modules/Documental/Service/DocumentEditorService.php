<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Users\Models\User;
use App\Services\OnlyOfficeJwt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Edición de documentos en el navegador con ONLYOFFICE Docs, sobre los archivos de Spaces.
 *
 * Flujo:
 *  1. El frontend pide la configuración del editor (config): se validan los permisos del usuario sobre la ruta
 *     y se devuelve la configuración firmada con JWT, con una URL temporal de Spaces para que el servidor de
 *     documentos descargue el archivo.
 *  2. Varias personas pueden editar a la vez: comparten la misma `key` mientras el archivo no cambie.
 *  3. Al cerrar el último editor (o al forzar guardado) ONLYOFFICE llama al callback firmado: se guarda la versión
 *     anterior en la carpeta oculta `.versiones` y se sube la nueva a la misma ruta, con registro en auditoría.
 */
class DocumentEditorService
{
    /** Formatos que se editan (OOXML, nativos de ONLYOFFICE) */
    public const EDITABLE = [
        'xlsx' => 'cell',
        'docx' => 'word',
        'pptx' => 'slide',
    ];

    /** Formatos que solo se visualizan en el editor (se descargan o se guardan como copia .xlsx/.docx/.pptx) */
    public const VIEWABLE = [
        'xls' => 'cell', 'xlsm' => 'cell', 'ods' => 'cell', 'csv' => 'cell',
        'doc' => 'word', 'docm' => 'word', 'odt' => 'word', 'rtf' => 'word', 'txt' => 'word',
        'ppt' => 'slide', 'pptm' => 'slide', 'odp' => 'slide',
        'pdf' => 'pdf',
    ];

    /** Plantillas en blanco para "Nuevo" (resources/templates) */
    public const NEW_FILE_TYPES = [
        'xlsx' => 'Hoja de cálculo',
        'docx' => 'Documento',
        'pptx' => 'Presentación',
    ];

    public const VERSIONS_FOLDER = '.versiones';

    public function __construct(
        protected DocumentalService $documental,
        protected OnlyOfficeJwt $jwt,
    ) {
    }

    public function isEnabled(): bool
    {
        return config('onlyoffice.url') !== '' && $this->jwt->isConfigured();
    }

    public static function documentTypeFor(string $extension): ?string
    {
        $extension = strtolower($extension);

        return self::EDITABLE[$extension] ?? self::VIEWABLE[$extension] ?? null;
    }

    public static function isEditable(string $extension): bool
    {
        return isset(self::EDITABLE[strtolower($extension)]);
    }

    /**
     * Configuración del editor para un archivo, firmada para ONLYOFFICE.
     */
    public function editorConfig(string $rawPath, User $user, ?int $projectId, ?string $ip = null): array
    {
        $this->assertEnabled();

        $path = $this->documental->normalizePath($rawPath);
        $permissions = $this->documental->getUserPermissionsForPath($user, $path);
        if (!$permissions['can_read']) {
            $this->deny($user, 'open_editor', $path, 'No tienes permisos para abrir este archivo.', $ip);
        }

        $disk = $this->documental->getDisk();
        if ($path === '' || !$disk->exists($path)) {
            throw new InvalidArgumentException('El archivo especificado no existe.');
        }

        $name = basename($path);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $documentType = self::documentTypeFor($extension);
        if ($documentType === null) {
            throw new InvalidArgumentException('Este tipo de archivo no se puede abrir en el editor.');
        }

        $size = (int) $disk->size($path);
        $maxMb = (int) config('onlyoffice.max_file_mb');
        if ($size > $maxMb * 1024 * 1024) {
            throw new InvalidArgumentException("El archivo supera los {$maxMb} MB que admite el editor; descárgalo para abrirlo.");
        }

        $lastModified = (int) $disk->lastModified($path);
        $canEdit = $permissions['can_upload'] && self::isEditable($extension);

        $config = [
            'documentType' => $documentType,
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => $extension,
                // Misma key = misma sesión de coedición; cambia cuando el archivo cambia
                'key' => $this->documentKey($path, $lastModified, $size),
                'title' => $name,
                'url' => $disk->temporaryUrl($path, now()->addHours(6)),
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'copy' => true,
                    'comment' => $canEdit,
                    'review' => $canEdit,
                    'chat' => $canEdit,
                ],
            ],
            'editorConfig' => [
                'mode' => $canEdit ? 'edit' : 'view',
                'lang' => 'es',
                'region' => 'es-CO',
                'callbackUrl' => $this->callbackUrl($path, $projectId),
                'coEditing' => ['mode' => 'fast', 'change' => true],
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'compactHeader' => true,
                    // El nombre del archivo ya está en la barra de la app
                    'toolbarHideFileName' => true,
                    'toolbarNoTabs' => false,
                    'hideRightMenu' => false,
                    // Tema con los colores de Elite (deploy/onlyoffice/themes/elite.json); si no existe, ONLYOFFICE usa el claro
                    'uiTheme' => config('onlyoffice.ui_theme'),
                    'unit' => 'cm',
                    'zoom' => 100,
                    'goback' => false,
                    'help' => false,
                    'feedback' => false,
                    'plugins' => false,
                ],
            ],
        ];
        $config['token'] = $this->jwt->encode($config);

        $this->documental->log($user, 'open_editor', $path, ['mode' => $config['editorConfig']['mode']], $ip);

        return [
            'document_server_url' => config('onlyoffice.url'),
            'config' => $config,
            'file' => [
                'name' => $name,
                'path' => $path,
                'location' => $this->documental->parentOf($path),
                'size' => $size,
                'last_modified' => $lastModified ? date('c', $lastModified) : null,
                'extension' => $extension,
                'document_type' => $documentType,
            ],
            'can_edit' => $canEdit,
            'read_only_reason' => $canEdit ? null : ($permissions['can_upload']
                ? 'Este formato se abre en modo lectura. Para editarlo, guarda una copia como ' . $this->modernExtension($documentType) . ' desde Archivo › Guardar como.'
                : 'Tienes permisos de solo lectura en esta carpeta.'),
        ];
    }

    /**
     * Callback de ONLYOFFICE. Devuelve true si se procesó (la respuesta a ONLYOFFICE debe ser {"error":0}).
     *
     * Estados: 1 editando · 2 listo para guardar (todos cerraron) · 3 error al guardar · 4 cerrado sin cambios ·
     * 6 guardado forzado (el documento sigue abierto) · 7 error en el guardado forzado.
     */
    public function handleCallback(string $rawPath, ?int $projectId, array $body, ?string $authorizationHeader): void
    {
        $this->assertEnabled();
        $payload = $this->verifiedPayload($body, $authorizationHeader);

        $status = (int) ($payload['status'] ?? 0);
        $path = $this->documental->normalizePath($rawPath);

        if (in_array($status, [3, 7], true)) {
            Log::warning('ONLYOFFICE no pudo guardar el documento', ['path' => $path, 'payload' => $payload]);

            return;
        }
        if (!in_array($status, [2, 6], true)) {
            return;
        }

        $user = User::find((int) ($payload['users'][0] ?? 0));
        if (!$user) {
            throw new InvalidArgumentException('El guardado no identifica un usuario válido.');
        }

        // Los permisos se revalidan al guardar: si al usuario le quitaron el acceso mientras editaba, no se guarda
        $this->documental->setActiveProject($projectId);
        if (!$this->documental->getUserPermissionsForPath($user, $path)['can_upload']) {
            $this->documental->log($user, 'denied', $path, ['attempted_action' => 'edit', 'reason' => 'Sin permiso de escritura al guardar'], null);
            throw new AuthorizationException('El usuario ya no tiene permiso para guardar en esta carpeta.');
        }
        if (!self::isEditable(pathinfo($path, PATHINFO_EXTENSION))) {
            throw new InvalidArgumentException('Este formato no se guarda desde el editor.');
        }

        $content = $this->downloadEditedFile((string) ($payload['url'] ?? ''));
        $disk = $this->documental->getDisk();
        $existed = $disk->exists($path);

        $versionPath = $existed ? $this->storePreviousVersion($path) : null;
        $disk->put($path, $content);

        $size = strlen($content);
        $this->documental->updateSearchIndex(fn (array $entries) => [
            ...array_values(array_filter($entries, fn (array $entry) => $entry[0] !== $path)),
            [$path, $size, time()],
        ]);
        $this->documental->clearPathCache($path);
        $this->documental->log($user, 'edit', $path, array_filter([
            'size' => $size,
            'editors' => array_values(array_map('intval', $payload['users'] ?? [])),
            'forced' => $status === 6,
            'previous_version' => $versionPath,
            'recreated' => !$existed ?: null,
        ], fn ($v) => $v !== null), null);
    }

    /**
     * Crea un archivo en blanco (hoja de cálculo, documento o presentación) en la carpeta.
     */
    public function createBlank(string $parentPath, string $name, string $type, User $user, ?string $ip = null): array
    {
        $type = strtolower($type);
        if (!isset(self::NEW_FILE_TYPES[$type])) {
            throw new InvalidArgumentException('Tipo de archivo no soportado.');
        }

        $directory = $this->documental->normalizePath($parentPath);
        $permissions = $this->documental->getUserPermissionsForPath($user, $directory);
        if (!$permissions['can_upload']) {
            $this->deny($user, 'create_file', $directory, 'No tienes permisos para crear archivos en esta carpeta.', $ip);
        }

        $baseName = trim(preg_replace('/\.' . $type . '$/i', '', trim($name)));
        if ($baseName === '' || str_starts_with($baseName, '.') || preg_match('#[\\\\/:*?"<>|]#', $baseName)) {
            throw new InvalidArgumentException('El nombre indicado no es válido.');
        }

        $template = resource_path("templates/blank.{$type}");
        if (!is_file($template)) {
            throw new InvalidArgumentException('No se encontró la plantilla del archivo en blanco.');
        }

        $filename = $this->documental->uniqueFilename($directory, $this->documental->sanitizeFilename("{$baseName}.{$type}"));
        $path = $this->documental->joinPath($directory, $filename);
        $content = (string) file_get_contents($template);
        $this->documental->getDisk()->put($path, $content);

        $this->documental->updateSearchIndex(fn (array $entries) => [...$entries, [$path, strlen($content), time()]]);
        $this->documental->clearPathCache($path);
        $this->documental->log($user, 'create_file', $path, ['type' => $type], $ip);

        return ['name' => $filename, 'path' => $path, 'type' => $type];
    }

    /**
     * Key de coedición: estable mientras el archivo no cambie (ruta + fecha + tamaño).
     */
    protected function documentKey(string $path, int $lastModified, int $size): string
    {
        return substr(hash('sha256', "{$path}|{$lastModified}|{$size}"), 0, 40);
    }

    /**
     * URL firmada (no adivinable) para que ONLYOFFICE avise el guardado de esta ruta concreta.
     */
    protected function callbackUrl(string $path, ?int $projectId): string
    {
        $relative = URL::signedRoute('documental.editor.callback', array_filter([
            'path' => $path,
            'project' => $projectId,
        ], fn ($v) => $v !== null), absolute: false);

        return config('onlyoffice.callback_base_url') . $relative;
    }

    /**
     * Con JWT activo ONLYOFFICE manda el payload firmado en `token` (cuerpo) o en el header Authorization.
     * Solo se confía en el contenido del token.
     */
    protected function verifiedPayload(array $body, ?string $authorizationHeader): array
    {
        $token = $body['token'] ?? null;
        if (!$token && $authorizationHeader && str_starts_with($authorizationHeader, 'Bearer ')) {
            $token = substr($authorizationHeader, 7);
        }
        if (!is_string($token) || $token === '') {
            throw new AuthorizationException('El callback no trae token.');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (InvalidArgumentException $e) {
            throw new AuthorizationException('Token del callback inválido: ' . $e->getMessage());
        }

        // En el header el payload viene envuelto en "payload"
        return isset($payload['payload']) && is_array($payload['payload']) ? $payload['payload'] : $payload;
    }

    /**
     * Descarga el archivo editado, solo desde el propio servidor de documentos.
     */
    protected function downloadEditedFile(string $url): string
    {
        $allowedHost = parse_url((string) config('onlyoffice.url'), PHP_URL_HOST);
        if ($url === '' || !$allowedHost || parse_url($url, PHP_URL_HOST) !== $allowedHost) {
            throw new InvalidArgumentException('La URL del archivo editado no pertenece al servidor de documentos.');
        }

        $response = Http::timeout(120)->get($url);
        if (!$response->successful() || $response->body() === '') {
            throw new InvalidArgumentException('No fue posible descargar el archivo editado.');
        }

        return $response->body();
    }

    /**
     * Copia la versión actual a {carpeta}/.versiones/{archivo}/{fecha}_{archivo} y conserva solo las últimas N.
     */
    protected function storePreviousVersion(string $path): string
    {
        $disk = $this->documental->getDisk();
        $name = basename($path);
        $versionsDir = $this->documental->joinPath($this->documental->joinPath($this->documental->parentOf($path), self::VERSIONS_FOLDER), $name);
        $versionPath = $this->documental->joinPath($versionsDir, now()->format('Ymd_His') . "_{$name}");

        $disk->copy($path, $versionPath);

        $keep = max(1, (int) config('onlyoffice.keep_versions'));
        $versions = collect($disk->files($versionsDir))->sort()->values();
        foreach ($versions->slice(0, max(0, $versions->count() - $keep)) as $old) {
            $disk->delete($old);
        }

        return $versionPath;
    }

    protected function modernExtension(string $documentType): string
    {
        return match ($documentType) {
            'cell' => '.xlsx',
            'slide' => '.pptx',
            default => '.docx',
        };
    }

    protected function deny(User $user, string $action, string $path, string $message, ?string $ip): never
    {
        if (config('documental.log_denied_attempts')) {
            $this->documental->log($user, 'denied', $path, ['attempted_action' => $action, 'reason' => $message], $ip);
        }

        throw new AuthorizationException($message);
    }

    protected function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw new InvalidArgumentException('El editor de documentos no está configurado (ONLYOFFICE_URL / ONLYOFFICE_JWT_SECRET).');
        }
    }
}
