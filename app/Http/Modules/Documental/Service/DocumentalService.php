<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Models\DocumentRolePermission;
use App\Http\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class DocumentalService
{
    /**
     * Permisos Spatie del módulo: definen QUÉ acciones puede ejecutar cada rol.
     * Las reglas por carpeta (document_role_permissions) definen DÓNDE, restringiendo carpetas a ciertos roles.
     */
    public const PERMISSION_VIEW = 'documental.ver';
    public const PERMISSION_UPLOAD = 'documental.subir';
    public const PERMISSION_CREATE_FOLDER = 'documental.crear_carpeta';
    public const PERMISSION_RENAME = 'documental.renombrar';
    public const PERMISSION_DELETE = 'documental.eliminar';
    public const PERMISSION_ADMIN = 'documental.administrar';

    public const PERMISSIONS = [
        self::PERMISSION_VIEW,
        self::PERMISSION_UPLOAD,
        self::PERMISSION_CREATE_FOLDER,
        self::PERMISSION_RENAME,
        self::PERMISSION_DELETE,
        self::PERMISSION_ADMIN,
    ];

    protected string $disk = 's3';

    protected const RULES_CACHE_KEY = 'documental_folder_rules';

    protected const SEARCH_INDEX_CACHE_KEY = 'documental_search_index';

    /**
     * Reglas por carpeta agrupadas por ruta (arrays planos), cargadas una sola vez por petición.
     */
    protected ?Collection $folderRules = null;

    /**
     * Permisos de rol ya resueltos por usuario, para no repetir las comprobaciones Spatie
     * por cada carpeta de un listado.
     */
    protected array $userContext = [];

    protected ?DocumentalSettingsService $settingsService = null;

    /**
     * Obtiene el disco configurado para DigitalOcean Spaces / S3.
     */
    public function getDisk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Store de caché del módulo (local por defecto, ver config/documental.php).
     */
    protected function cache(): Repository
    {
        return Cache::store(config('documental.cache_store'));
    }

    /**
     * Normaliza una ruta evitando directory traversal y barras duplicadas.
     */
    public function normalizePath(?string $path): string
    {
        if (empty($path) || $path === '/' || $path === '.') {
            return '';
        }

        // Reemplazar diagonales invertidas y barras duplicadas
        $cleaned = str_replace('\\', '/', $path);
        $cleaned = preg_replace('#/+#', '/', $cleaned);
        $cleaned = trim($cleaned, '/');

        // Evitar directory traversal
        $parts = [];
        foreach (explode('/', $cleaned) as $segment) {
            if ($segment === '..' || $segment === '.') {
                continue;
            }
            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Determina los permisos efectivos de un usuario sobre una ruta:
     * 1. Sin `documental.ver` no hay acceso. Con `documental.administrar` hay acceso total.
     * 2. Las acciones disponibles salen de los permisos Spatie de sus roles.
     * 3. Si la ruta (o un ancestro) tiene reglas por carpeta, la carpeta está restringida:
     *    solo acceden los roles listados y las acciones quedan limitadas por los flags de la regla.
     */
    public function getUserPermissionsForPath(?User $user, string $path): array
    {
        $path = $this->normalizePath($path);

        if (!$user) {
            return $this->noAccess();
        }

        $context = $this->userContext($user);
        if (!$context['view']) {
            return $this->noAccess();
        }

        $rules = $this->findApplicableRules($path);

        if ($context['admin']) {
            return [
                'can_read' => true,
                'can_upload' => true,
                'can_create_folder' => true,
                'can_rename' => true,
                'can_delete' => true,
                'is_admin' => true,
                'restricted' => $rules !== null,
            ];
        }

        $permissions = $context['base'];

        if ($rules === null) {
            return $permissions;
        }

        $userRules = $rules->whereIn('role_id', $context['role_ids']);
        $grants = fn (string $flag) => $userRules->contains(fn (array $rule) => (bool) $rule[$flag]);

        if (!$grants('can_read')) {
            return array_merge($this->noAccess(), ['restricted' => true]);
        }

        return [
            'can_read' => true,
            'can_upload' => $permissions['can_upload'] && $grants('can_upload'),
            'can_create_folder' => $permissions['can_create_folder'] && $grants('can_create_folder'),
            // Renombrar modifica el contenido de la carpeta, así que exige permiso de escritura en ella
            'can_rename' => $permissions['can_rename'] && $grants('can_upload'),
            'can_delete' => $permissions['can_delete'] && $grants('can_delete'),
            'is_admin' => false,
            'restricted' => true,
        ];
    }

    protected function userContext(User $user): array
    {
        return $this->userContext[$user->id] ??= [
            'view' => $user->can(self::PERMISSION_VIEW),
            'admin' => $user->can(self::PERMISSION_ADMIN),
            'role_ids' => $user->roles->pluck('id')->all(),
            'base' => [
                'can_read' => true,
                'can_upload' => $user->can(self::PERMISSION_UPLOAD),
                'can_create_folder' => $user->can(self::PERMISSION_CREATE_FOLDER),
                'can_rename' => $user->can(self::PERMISSION_RENAME),
                'can_delete' => $user->can(self::PERMISSION_DELETE),
                'is_admin' => false,
                'restricted' => false,
            ],
        ];
    }

    protected function noAccess(): array
    {
        return [
            'can_read' => false,
            'can_upload' => false,
            'can_create_folder' => false,
            'can_rename' => false,
            'can_delete' => false,
            'is_admin' => false,
            'restricted' => false,
        ];
    }

    protected function getFolderRules(): Collection
    {
        return $this->folderRules ??= collect($this->cache()->rememberForever(
            self::RULES_CACHE_KEY,
            fn () => DocumentRolePermission::all(['role_id', 'folder_path', 'can_read', 'can_upload', 'can_create_folder', 'can_delete'])->toArray()
        ))->groupBy('folder_path');
    }

    protected function forgetFolderRules(): void
    {
        $this->cache()->forget(self::RULES_CACHE_KEY);
        $this->folderRules = null;
    }

    /**
     * Devuelve las reglas de la carpeta restringida más cercana (la propia ruta o su ancestro más próximo).
     */
    protected function findApplicableRules(string $path): ?Collection
    {
        $rules = $this->getFolderRules();
        if ($path === '' || $rules->isEmpty()) {
            return null;
        }

        $segments = explode('/', $path);
        while (count($segments) > 0) {
            $candidate = implode('/', $segments);
            if ($rules->has($candidate)) {
                return $rules->get($candidate);
            }
            array_pop($segments);
        }

        return null;
    }

    /**
     * Evita que una operación sobre una carpeta afecte subcarpetas restringidas sobre las que el usuario no tiene el permiso.
     */
    protected function assertCanAffectDescendants(User $user, string $path, string $flag, string $action, ?string $ip): void
    {
        foreach ($this->getFolderRules()->keys() as $rulePath) {
            if (str_starts_with($rulePath, $path . '/')) {
                $this->authorize(
                    $this->getUserPermissionsForPath($user, $rulePath)[$flag],
                    'La carpeta contiene subcarpetas restringidas sobre las que no tienes permisos.',
                    $user, $action, $path, $ip
                );
            }
        }
    }

    /**
     * Lanza AuthorizationException si la acción no está permitida, dejando registro del intento en auditoría.
     */
    protected function authorize(bool $allowed, string $message, User $user, string $action, string $path, ?string $ip): void
    {
        if ($allowed) {
            return;
        }

        if (config('documental.log_denied_attempts')) {
            $this->log($user, 'denied', $path, ['attempted_action' => $action, 'reason' => $message], $ip);
        }

        throw new AuthorizationException($message);
    }

    /**
     * Impide renombrar o eliminar desde la app carpetas con demasiados archivos: la operación se hace archivo por
     * archivo en S3 y en carpetas grandes superaría el tiempo de la petición, dejándola a medias.
     */
    protected function assertFolderWithinOperationLimit(string $path, string $verb): void
    {
        $limit = (int) $this->settings()->all()['max_folder_operation_files'];
        $count = 0;

        foreach ($this->getDisk()->listContents($path, true) as $item) {
            if ($item->isFile() && ++$count > $limit) {
                throw new InvalidArgumentException(
                    "La carpeta tiene más de {$limit} archivos; no se puede {$verb} desde la aplicación porque la operación "
                    . 'tardaría demasiado y podría quedar incompleta. Hazlo por partes (subcarpetas) o pide a un administrador '
                    . 'que la ejecute directamente en el almacenamiento.'
                );
            }
        }
    }

    protected function settings(): DocumentalSettingsService
    {
        return $this->settingsService ??= app(DocumentalSettingsService::class);
    }

    /**
     * Rechaza un archivo que incumple la configuración (tamaño o extensión) dejando registro del intento.
     */
    protected function rejectInvalidFile(string $reason, User $user, string $action, string $path, ?string $ip): never
    {
        if (config('documental.log_denied_attempts')) {
            $this->log($user, 'denied', $path, ['attempted_action' => $action, 'reason' => $reason], $ip);
        }

        throw new InvalidArgumentException($reason);
    }

    /**
     * Limpia la caché de navegación para una ruta y su carpeta padre.
     */
    public function clearPathCache(string $path): void
    {
        $normalized = $this->normalizePath($path);
        $this->cache()->forget($this->browseCacheKey($normalized));
        $this->cache()->forget($this->browseCacheKey($this->parentOf($normalized)));
    }

    protected function browseCacheKey(string $path): string
    {
        return 's3_browse_' . md5($path);
    }

    /**
     * Navega y lista los contenidos (carpetas y archivos) del S3.
     * El listado completo de la carpeta se cachea (compartido entre usuarios); la búsqueda,
     * los permisos y el filtrado de carpetas restringidas se aplican por usuario sobre la caché.
     * Con $recursive la búsqueda incluye todas las subcarpetas usando el índice del bucket.
     */
    public function browse(
        string $rawPath,
        User $user,
        ?string $search = null,
        bool $forceRefresh = false,
        ?string $ip = null,
        bool $recursive = false
    ): array {
        $path = $this->normalizePath($rawPath);
        $permissions = $this->getUserPermissionsForPath($user, $path);
        $this->authorize($permissions['can_read'], 'No tienes acceso a esta carpeta.', $user, 'browse', $path, $ip);

        $terms = $this->searchTerms($search);
        if ($terms !== [] && $recursive) {
            return $this->searchRecursive($path, $terms, $user, $permissions, $forceRefresh);
        }

        $cacheKey = $this->browseCacheKey($path);
        if ($forceRefresh) {
            $this->cache()->forget($cacheKey);
        }

        $result = $this->cache()->get($cacheKey);
        if (!is_array($result)) {
            $result = $this->listDirectory($path);
            $this->cache()->put($cacheKey, $result, config('documental.listing_ttl'));
        }

        if ($terms !== []) {
            $matches = fn (array $item) => $this->matchesTerms($item['name'], $terms);
            $result['folders'] = array_filter($result['folders'], $matches);
            $result['files'] = array_values(array_filter($result['files'], $matches));
            $result['total_files'] = count($result['files']);
            $result['total_size'] = array_sum(array_column($result['files'], 'size'));
            $result['total_size_formatted'] = $this->formatBytes($result['total_size']);
            $result['search'] = [
                'query' => trim((string) $search),
                'recursive' => false,
                'total_matches' => count($result['folders']) + count($result['files']),
                'truncated' => false,
                'index_built_at' => null,
            ];
        }

        $rules = $this->getFolderRules();
        $visibleFolders = [];
        foreach ($result['folders'] as $folder) {
            if ($this->getUserPermissionsForPath($user, $folder['path'])['can_read']) {
                $folder['restricted'] = $rules->has($folder['path']);
                $visibleFolders[] = $folder;
            }
        }

        $result['folders'] = $visibleFolders;
        $result['total_folders'] = count($visibleFolders);
        $result['permissions'] = $permissions;

        return $result;
    }

    /**
     * Palabras de búsqueda normalizadas (sin tildes, minúsculas). Todas deben aparecer en el nombre.
     */
    protected function searchTerms(?string $search): array
    {
        return array_values(array_filter(preg_split('/\s+/', $this->normalizeForSearch((string) $search))));
    }

    protected function normalizeForSearch(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }

    protected function matchesTerms(string $name, array $terms): bool
    {
        $normalized = $this->normalizeForSearch($name);
        foreach ($terms as $term) {
            if (!str_contains($normalized, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Busca por nombre en la carpeta y todas sus subcarpetas, sobre el índice cacheado del bucket.
     * Solo devuelve elementos que el usuario puede ver y como máximo `search_max_results`.
     */
    protected function searchRecursive(string $path, array $terms, User $user, array $permissions, bool $forceRefresh): array
    {
        $index = $this->searchIndex($forceRefresh);
        $prefix = $path === '' ? '' : $path . '/';
        $limit = (int) config('documental.search_max_results');
        $disk = $this->getDisk();
        $rules = $this->getFolderRules();

        $folderPaths = [];
        $files = [];
        $totalMatches = 0;

        foreach ($index['files'] as [$filePath, $size, $lastModified]) {
            if ($prefix !== '' && !str_starts_with($filePath, $prefix)) {
                continue;
            }

            // Registrar las carpetas intermedias (dentro del alcance) para poder buscarlas por nombre
            $dir = $this->parentOf($filePath);
            while ($dir !== $path && $dir !== '' && !isset($folderPaths[$dir])) {
                $folderPaths[$dir] = true;
                $dir = $this->parentOf($dir);
            }

            $name = basename($filePath);
            if (str_starts_with($name, '.') || str_contains($filePath, '/.') || !$this->matchesTerms($name, $terms)) {
                continue;
            }
            if (!$this->getUserPermissionsForPath($user, $this->parentOf($filePath))['can_read']) {
                continue;
            }

            $totalMatches++;
            if (count($files) >= $limit) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $previewUrl = null;
            try {
                $previewUrl = $disk->temporaryUrl($filePath, now()->addHours(2));
            } catch (\Throwable $e) {
            }

            $files[] = [
                'name' => $name,
                'path' => $filePath,
                'type' => 'file',
                'location' => $this->parentOf($filePath),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'mime_type' => $this->guessMimeType($extension),
                'extension' => $extension,
                'last_modified' => $lastModified ? date('c', $lastModified) : null,
                'preview_url' => $previewUrl,
            ];
        }

        $folders = [];
        foreach (array_keys($folderPaths) as $folderPath) {
            $name = basename($folderPath);
            if (str_contains('/' . $folderPath, '/.') || !$this->matchesTerms($name, $terms)) {
                continue;
            }
            if (!$this->getUserPermissionsForPath($user, $folderPath)['can_read']) {
                continue;
            }

            $totalMatches++;
            if (count($folders) < $limit) {
                $folders[] = [
                    'name' => $name,
                    'path' => $folderPath,
                    'type' => 'folder',
                    'location' => $this->parentOf($folderPath),
                    'restricted' => $rules->has($folderPath),
                ];
            }
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['path'], $b['path']));
        usort($files, fn ($a, $b) => strcasecmp($a['path'], $b['path']));
        $totalSize = array_sum(array_column($files, 'size'));

        return [
            'current_path' => $path,
            'parent_path' => $path === '' ? null : $this->parentOf($path),
            'breadcrumbs' => $this->breadcrumbsFor($path),
            'folders' => $folders,
            'files' => $files,
            'total_folders' => count($folders),
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'permissions' => $permissions,
            'search' => [
                'query' => implode(' ', $terms),
                'recursive' => true,
                'total_matches' => $totalMatches,
                'truncated' => $totalMatches > count($folders) + count($files),
                'index_built_at' => $index['built_at'],
            ],
        ];
    }

    /**
     * Aplica un cambio hecho desde la app al índice ya construido, para que la búsqueda lo refleje
     * sin tener que volver a listar todo el bucket. Si el índice no existe no hace nada.
     */
    protected function updateSearchIndex(callable $transform): void
    {
        $index = $this->cache()->get(self::SEARCH_INDEX_CACHE_KEY);
        if (!is_array($index)) {
            return;
        }

        $index['files'] = $transform($index['files']);
        $this->cache()->put(self::SEARCH_INDEX_CACHE_KEY, $index, config('documental.search_index_ttl'));
    }

    /**
     * Índice de todos los archivos del bucket ([ruta, tamaño, fecha]) para la búsqueda en subcarpetas.
     * Se construye con un listado recursivo (unos segundos con ~30.000 objetos) y se cachea `search_index_ttl`.
     */
    public function searchIndex(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->cache()->forget(self::SEARCH_INDEX_CACHE_KEY);
        }

        return $this->cache()->remember(self::SEARCH_INDEX_CACHE_KEY, config('documental.search_index_ttl'), function () {
            $files = [];
            foreach ($this->getDisk()->listContents('', true) as $item) {
                if ($item->isFile()) {
                    $files[] = [$item->path(), $item->fileSize() ?? 0, $item->lastModified()];
                }
            }

            return ['built_at' => now()->toIso8601String(), 'files' => $files];
        });
    }

    /**
     * Lista un directorio con una sola petición ListObjectsV2; tamaño, fecha y MIME salen de los metadatos en memoria.
     */
    protected function listDirectory(string $path): array
    {
        $disk = $this->getDisk();
        $folders = [];
        $files = [];
        $totalSize = 0;

        foreach ($disk->listContents($path, false) as $item) {
            $itemPath = $item->path();
            $itemName = basename($itemPath);

            // Filtrar archivos internos (.keep, ._ de macOS, etc.)
            if (str_starts_with($itemName, '.')) {
                continue;
            }

            if ($item->isDir()) {
                $folders[] = [
                    'name' => $itemName,
                    'path' => $itemPath,
                    'type' => 'folder',
                ];
                continue;
            }

            $size = $item->fileSize() ?? 0;
            $totalSize += $size;
            $lastModified = $item->lastModified();
            $extension = strtolower(pathinfo($itemName, PATHINFO_EXTENSION));

            $previewUrl = null;
            try {
                // Firmado HMAC-SHA256 en memoria local, sin llamadas de red a S3
                $previewUrl = $disk->temporaryUrl($itemPath, now()->addHours(2));
            } catch (\Throwable $e) {
            }

            $files[] = [
                'name' => $itemName,
                'path' => $itemPath,
                'type' => 'file',
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'mime_type' => $this->guessMimeType($extension),
                'extension' => $extension,
                'last_modified' => $lastModified ? date('c', $lastModified) : null,
                'preview_url' => $previewUrl,
            ];
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $breadcrumbs = $this->breadcrumbsFor($path);

        $parentPath = null;
        if ($path !== '') {
            $parentPath = $this->parentOf($path);
        }

        return [
            'current_path' => $path,
            'parent_path' => $parentPath,
            'breadcrumbs' => $breadcrumbs,
            'folders' => $folders,
            'files' => $files,
            'total_folders' => count($folders),
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Crea una nueva carpeta en S3 / DigitalOcean Spaces.
     */
    public function createFolder(string $parentPath, string $folderName, User $user, ?string $ip = null): array
    {
        $normalizedParent = $this->normalizePath($parentPath);
        $permissions = $this->getUserPermissionsForPath($user, $normalizedParent);
        $this->authorize($permissions['can_create_folder'], 'No tienes permisos para crear carpetas en este directorio.', $user, 'create_folder', $normalizedParent, $ip);

        $cleanFolderName = $this->cleanItemName($folderName);
        $fullPath = $this->joinPath($normalizedParent, $cleanFolderName);

        $disk = $this->getDisk();
        if ($disk->directoryExists($fullPath)) {
            throw new InvalidArgumentException('Ya existe una carpeta con ese nombre en este directorio.');
        }

        // S3 no tiene carpetas reales: el objeto marcador .keep hace visible la carpeta mientras esté vacía
        $disk->put($fullPath . '/.keep', '');

        $this->log($user, 'create_folder', $fullPath, ['parent' => $normalizedParent, 'folder_name' => $cleanFolderName], $ip);
        $this->updateSearchIndex(fn (array $files) => [...$files, [$fullPath . '/.keep', 0, time()]]);
        $this->clearPathCache($fullPath);

        return [
            'message' => 'Carpeta creada exitosamente.',
            'name' => $cleanFolderName,
            'path' => $fullPath,
        ];
    }

    /**
     * Sube uno o varios archivos a la ruta indicada. Si ya existe un archivo con el mismo nombre
     * se guarda como "nombre (1).ext" para no sobrescribir documentos existentes.
     */
    public function uploadFiles(string $targetPath, array $files, User $user, ?string $ip = null): array
    {
        $normalizedPath = $this->normalizePath($targetPath);
        $permissions = $this->getUserPermissionsForPath($user, $normalizedPath);
        $this->authorize($permissions['can_upload'], 'No tienes permisos para subir archivos en este directorio.', $user, 'upload', $normalizedPath, $ip);

        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile && $file->isValid()));
        if (count($files) === 0) {
            throw new InvalidArgumentException('No se recibió ningún archivo válido.');
        }

        // Se validan todos antes de subir para no dejar cargas a medias
        foreach ($files as $file) {
            $reason = $this->settings()->validateUpload($file->getClientOriginalName(), (int) $file->getSize());
            if ($reason !== null) {
                $this->rejectInvalidFile($reason, $user, 'upload', $this->joinPath($normalizedPath, $file->getClientOriginalName()), $ip);
            }
        }

        $disk = $this->getDisk();
        $uploaded = [];

        foreach ($files as $file) {

            $originalName = $file->getClientOriginalName();
            $safeName = $this->uniqueFilename($normalizedPath, $this->sanitizeFilename($originalName));
            $filePath = $this->joinPath($normalizedPath, $safeName);

            $disk->putFileAs($normalizedPath, $file, $safeName);

            $this->updateSearchIndex(fn (array $entries) => [...$entries, [$filePath, (int) $file->getSize(), time()]]);

            $this->log($user, 'upload', $filePath, [
                'original_name' => $originalName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ], $ip);

            $uploaded[] = [
                'name' => $safeName,
                'path' => $filePath,
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
            ];
        }

        $this->clearPathCache($normalizedPath);

        return [
            'message' => count($uploaded) . ' archivo(s) subido(s) exitosamente.',
            'uploaded' => $uploaded,
        ];
    }

    /**
     * Elimina un archivo o una carpeta (con todo su contenido) en S3 / Spaces.
     */
    public function deleteItem(string $itemPath, string $type, User $user, ?string $ip = null): array
    {
        $path = $this->normalizePath($itemPath);
        if ($path === '') {
            throw new InvalidArgumentException('No se puede eliminar la raíz del repositorio.');
        }

        $permissions = $this->getUserPermissionsForPath($user, $path);
        $this->authorize($permissions['can_delete'], 'No tienes permisos para eliminar este elemento.', $user, 'delete', $path, $ip);

        $disk = $this->getDisk();

        if ($type === 'folder') {
            $this->assertCanAffectDescendants($user, $path, 'can_delete', 'delete', $ip);
            $this->assertFolderWithinOperationLimit($path, 'eliminar');
            $disk->deleteDirectory($path);
            $this->folderRulesUnder($path)->delete();
            $this->forgetFolderRules();
        } else {
            if (!$disk->exists($path)) {
                throw new InvalidArgumentException('El archivo especificado no existe.');
            }
            $disk->delete($path);
        }

        $this->log($user, 'delete', $path, ['type' => $type], $ip);
        $this->updateSearchIndex(fn (array $files) => array_values(array_filter(
            $files,
            fn (array $entry) => $entry[0] !== $path && !str_starts_with($entry[0], $path . '/')
        )));
        $this->clearPathCache($path);

        return [
            'message' => ($type === 'folder' ? 'Carpeta eliminada' : 'Archivo eliminado') . ' exitosamente.',
            'path' => $path,
        ];
    }

    /**
     * Renombra un archivo o carpeta. En S3 renombrar una carpeta implica copiar cada objeto
     * a la nueva ruta y eliminar los originales; las reglas de la carpeta se trasladan con ella.
     */
    public function renameItem(string $oldPath, string $newName, string $type, User $user, ?string $ip = null): array
    {
        $normalizedOld = $this->normalizePath($oldPath);
        if ($normalizedOld === '') {
            throw new InvalidArgumentException('No se puede renombrar la raíz del repositorio.');
        }

        $permissions = $this->getUserPermissionsForPath($user, $normalizedOld);
        $this->authorize($permissions['can_rename'], 'No tienes permisos para renombrar este elemento.', $user, 'rename', $normalizedOld, $ip);

        $cleanNewName = $this->cleanItemName($newName);
        if ($type === 'file') {
            $cleanNewName = $this->sanitizeFilename($cleanNewName);
            $reason = $this->settings()->validateExtension($cleanNewName);
            if ($reason !== null) {
                $this->rejectInvalidFile($reason, $user, 'rename', $normalizedOld, $ip);
            }
        }

        $normalizedNew = $this->joinPath($this->parentOf($normalizedOld), $cleanNewName);
        if ($normalizedNew === $normalizedOld) {
            throw new InvalidArgumentException('El nuevo nombre es igual al actual.');
        }

        $disk = $this->getDisk();

        if ($type === 'folder') {
            if ($disk->directoryExists($normalizedNew)) {
                throw new InvalidArgumentException('Ya existe una carpeta con ese nombre en este directorio.');
            }
            $this->assertCanAffectDescendants($user, $normalizedOld, 'can_rename', 'rename', $ip);
            $this->assertFolderWithinOperationLimit($normalizedOld, 'renombrar');

            foreach ($disk->allFiles($normalizedOld) as $file) {
                $disk->copy($file, $normalizedNew . substr($file, strlen($normalizedOld)));
            }
            $disk->deleteDirectory($normalizedOld);

            DB::transaction(function () use ($normalizedOld, $normalizedNew) {
                foreach ($this->folderRulesUnder($normalizedOld)->get() as $rule) {
                    $rule->update(['folder_path' => $normalizedNew . substr($rule->folder_path, strlen($normalizedOld))]);
                }
            });
            $this->forgetFolderRules();
        } else {
            if ($disk->exists($normalizedNew)) {
                throw new InvalidArgumentException('Ya existe un archivo con ese nombre en este directorio.');
            }
            $disk->move($normalizedOld, $normalizedNew);
        }

        $this->log($user, 'rename', $normalizedNew, ['from' => $normalizedOld, 'to' => $normalizedNew, 'type' => $type], $ip);
        $this->updateSearchIndex(fn (array $files) => array_map(function (array $entry) use ($normalizedOld, $normalizedNew) {
            if ($entry[0] === $normalizedOld || str_starts_with($entry[0], $normalizedOld . '/')) {
                $entry[0] = $normalizedNew . substr($entry[0], strlen($normalizedOld));
            }

            return $entry;
        }, $files));
        $this->clearPathCache($normalizedOld);
        $this->clearPathCache($normalizedNew);

        return [
            'message' => 'Elemento renombrado correctamente.',
            'old_path' => $normalizedOld,
            'new_path' => $normalizedNew,
            'new_name' => $cleanNewName,
        ];
    }

    /**
     * Genera una URL temporal firmada que fuerza la descarga del archivo.
     */
    public function getDownloadUrl(string $filePath, User $user, ?string $ip = null): array
    {
        $path = $this->normalizePath($filePath);
        $permissions = $this->getUserPermissionsForPath($user, $path);
        $this->authorize($permissions['can_read'], 'No tienes permisos para descargar este archivo.', $user, 'download', $path, $ip);

        $disk = $this->getDisk();
        if (!$disk->exists($path)) {
            throw new InvalidArgumentException('El archivo especificado no existe.');
        }

        $filename = basename($path);
        $url = $disk->temporaryUrl($path, now()->addMinutes(60), [
            'ResponseContentDisposition' => "attachment; filename*=UTF-8''" . rawurlencode($filename),
        ]);

        $this->log($user, 'download', $path, ['filename' => $filename], $ip);

        return [
            'download_url' => $url,
            'name' => $filename,
            'expires_in_minutes' => 60,
        ];
    }

    /**
     * Carpetas restringidas con los roles que tienen acceso a cada una.
     */
    public function listRestrictedFolders(): array
    {
        return DocumentRolePermission::with('role:id,name')
            ->orderBy('folder_path')
            ->get()
            ->groupBy('folder_path')
            ->map(fn (Collection $rules, string $folderPath) => [
                'folder_path' => $folderPath,
                'rules' => $rules->map(fn (DocumentRolePermission $rule) => [
                    'role_id' => $rule->role_id,
                    'role_name' => $rule->role?->name,
                    'can_read' => $rule->can_read,
                    'can_upload' => $rule->can_upload,
                    'can_create_folder' => $rule->can_create_folder,
                    'can_delete' => $rule->can_delete,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Reemplaza las reglas de una carpeta. Solo se guardan los roles con acceso de lectura;
     * una lista vacía elimina la restricción y la carpeta vuelve a regirse por los permisos de cada rol.
     */
    public function syncFolderRules(string $folderPath, array $rules, User $user, ?string $ip = null): array
    {
        $path = $this->normalizePath($folderPath);
        if ($path === '') {
            throw new InvalidArgumentException('Las restricciones se aplican sobre una carpeta específica, no sobre la raíz.');
        }
        if (!$this->getDisk()->directoryExists($path)) {
            throw new InvalidArgumentException('La carpeta indicada no existe en el repositorio.');
        }

        $rules = collect($rules)
            ->filter(fn (array $rule) => !empty($rule['can_read']))
            ->unique('role_id')
            ->map(fn (array $rule) => [
                'role_id' => (int) $rule['role_id'],
                'can_read' => true,
                'can_upload' => (bool) ($rule['can_upload'] ?? false),
                'can_create_folder' => (bool) ($rule['can_create_folder'] ?? false),
                'can_delete' => (bool) ($rule['can_delete'] ?? false),
            ])
            ->values();

        DB::transaction(function () use ($path, $rules) {
            DocumentRolePermission::where('folder_path', $path)->delete();
            foreach ($rules as $rule) {
                DocumentRolePermission::create($rule + ['folder_path' => $path]);
            }
        });
        $this->forgetFolderRules();

        $roleNames = Role::whereIn('id', $rules->pluck('role_id'))->pluck('name', 'id');
        $this->log($user, 'restrictions', $path, [
            'restricted' => $rules->isNotEmpty(),
            'rules' => $rules->map(fn (array $rule) => $rule + ['role_name' => $roleNames[$rule['role_id']] ?? null])->all(),
        ], $ip);

        return [
            'message' => $rules->isEmpty()
                ? 'Se quitó la restricción de la carpeta.'
                : 'Restricción guardada: ' . $rules->count() . ' rol(es) con acceso.',
            'folder_path' => $path,
            'restricted' => $rules->isNotEmpty(),
        ];
    }

    /**
     * Consulta de reglas de una carpeta y todas sus subcarpetas.
     */
    protected function folderRulesUnder(string $path)
    {
        $escaped = addcslashes($path, '%_\\');

        return DocumentRolePermission::where('folder_path', $path)
            ->orWhere('folder_path', 'like', $escaped . '/%');
    }

    protected function log(User $user, string $action, string $path, array $details, ?string $ip): void
    {
        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'path' => $path,
            'details' => $details,
            'ip_address' => $ip,
        ]);
    }

    protected function cleanItemName(string $name): string
    {
        $clean = trim(str_replace(['/', '\\'], '', $name));
        if ($clean === '' || $clean === '.' || $clean === '..' || str_starts_with($clean, '.')) {
            throw new InvalidArgumentException('El nombre indicado no es válido.');
        }

        return $clean;
    }

    protected function uniqueFilename(string $directory, string $filename): string
    {
        $disk = $this->getDisk();
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';

        $candidate = $filename;
        $counter = 1;
        while ($disk->exists($this->joinPath($directory, $candidate))) {
            $candidate = "{$base} ({$counter}){$suffix}";
            $counter++;
        }

        return $candidate;
    }

    protected function breadcrumbsFor(string $path): array
    {
        $breadcrumbs = [['name' => 'Raíz', 'path' => '']];
        if ($path !== '') {
            $accum = '';
            foreach (explode('/', $path) as $segment) {
                $accum = $this->joinPath($accum, $segment);
                $breadcrumbs[] = ['name' => $segment, 'path' => $accum];
            }
        }

        return $breadcrumbs;
    }

    protected function joinPath(string $directory, string $name): string
    {
        return $directory === '' ? $name : $directory . '/' . $name;
    }

    protected function parentOf(string $path): string
    {
        $parent = dirname($path);

        return $parent === '.' ? '' : $parent;
    }

    /**
     * Limpia un nombre de archivo para evitar caracteres problemáticos en URLs / S3.
     */
    protected function sanitizeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = str_replace(['/', '\\', '..', '#', '?', '%', '&'], '-', $filename);
        return $filename;
    }

    /**
     * Formatea bytes a unidades legibles (KB, MB, GB).
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }

    /**
     * Deduce el tipo MIME según la extensión de forma instantánea sin peticiones de red.
     */
    public function guessMimeType(string $extension): string
    {
        $map = [
            // Documentos
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'rtf' => 'application/rtf',
            // Imágenes
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            // Audio y Video
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            // Comprimidos
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            // Planos / CAD y código
            'dwg' => 'application/acad',
            'dxf' => 'application/dxf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
        ];

        return $map[strtolower(ltrim($extension, '.'))] ?? 'application/octet-stream';
    }
}
