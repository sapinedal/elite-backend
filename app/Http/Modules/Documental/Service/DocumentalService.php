<?php

namespace App\Http\Modules\Documental\Service;

use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Models\DocumentAreaPermission;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentalService
{
    protected string $disk = 's3';

    /**
     * Obtiene el disco configurado para DigitalOcean Spaces / S3.
     */
    public function getDisk()
    {
        return Storage::disk($this->disk);
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
     * Determina los permisos efectivos de un usuario sobre una ruta dada.
     */
    public function getUserPermissionsForPath(?User $user, string $path): array
    {
        // Si no hay usuario autenticado, por seguridad denegamos acciones de escritura
        if (!$user) {
            return [
                'can_read' => true,
                'can_upload' => false,
                'can_create_folder' => false,
                'can_delete' => false,
                'is_admin' => false,
                'role_type' => 'guest',
            ];
        }

        // Cargar posición y área si no están cargadas
        if (!$user->relationLoaded('position') || !$user->relationLoaded('area')) {
            $user->load(['position', 'area']);
        }

        $positionName = strtolower(optional($user->position)->name ?? '');
        $areaName = strtolower(optional($user->area)->name ?? '');
        $userName = strtolower($user->name ?? '');

        // Rol Administrador / Dirección / Gerencia
        $isAdmin = str_contains($positionName, 'director') ||
                   str_contains($positionName, 'gerente') ||
                   str_contains($positionName, 'coordinador') ||
                   str_contains($userName, 'admin') ||
                   $user->email === 'admin@elite.com';

        if ($isAdmin) {
            return [
                'can_read' => true,
                'can_upload' => true,
                'can_create_folder' => true,
                'can_delete' => true,
                'is_admin' => true,
                'role_type' => 'admin',
            ];
        }

        // Si el usuario pertenece a un área, consultamos reglas específicas en la base de datos
        if ($user->area_id) {
            // Buscamos coincidencia exacta o heredada por prefijo de ruta
            $permissions = DocumentAreaPermission::where('area_id', $user->area_id)->get();

            // 1. Coincidencia exacta
            $exactRule = $permissions->firstWhere('folder_path', $path);
            if ($exactRule) {
                return [
                    'can_read' => (bool)$exactRule->can_read,
                    'can_upload' => (bool)$exactRule->can_upload,
                    'can_create_folder' => (bool)$exactRule->can_create_folder,
                    'can_delete' => (bool)$exactRule->can_delete,
                    'is_admin' => false,
                    'role_type' => 'area_rule',
                ];
            }

            // 2. Coincidencia por carpetas padre
            $pathSegments = explode('/', $path);
            while (count($pathSegments) > 0) {
                array_pop($pathSegments);
                $parentPath = implode('/', $pathSegments);
                $parentRule = $permissions->firstWhere('folder_path', $parentPath);
                if ($parentRule) {
                    return [
                        'can_read' => (bool)$parentRule->can_read,
                        'can_upload' => (bool)$parentRule->can_upload,
                        'can_create_folder' => (bool)$parentRule->can_create_folder,
                        'can_delete' => (bool)$parentRule->can_delete,
                        'is_admin' => false,
                        'role_type' => 'inherited_rule',
                    ];
                }
            }

            // 3. Regla comodín de área (*)
            $wildcardRule = $permissions->firstWhere('folder_path', '*');
            if ($wildcardRule) {
                return [
                    'can_read' => (bool)$wildcardRule->can_read,
                    'can_upload' => (bool)$wildcardRule->can_upload,
                    'can_create_folder' => (bool)$wildcardRule->can_create_folder,
                    'can_delete' => (bool)$wildcardRule->can_delete,
                    'is_admin' => false,
                    'role_type' => 'wildcard_rule',
                ];
            }

            // 4. Si la ruta contiene el nombre de su área (ej. "Comercial"), le damos permisos de lectura y subida por defecto
            if ($areaName && str_contains(strtolower($path), $areaName)) {
                return [
                    'can_read' => true,
                    'can_upload' => true,
                    'can_create_folder' => true,
                    'can_delete' => false,
                    'is_admin' => false,
                    'role_type' => 'area_match',
                ];
            }
        }

        // Permisos por defecto para empleados en navegación general
        return [
            'can_read' => true,
            'can_upload' => false,
            'can_create_folder' => false,
            'can_delete' => false,
            'is_admin' => false,
            'role_type' => 'standard_employee',
        ];
    }

    /**
     * Limpia la caché de navegación para una ruta y sus ancestros.
     */
    public function clearPathCache(string $path): void
    {
        $normalized = $this->normalizePath($path);
        \Illuminate\Support\Facades\Cache::forget('s3_browse_' . md5($normalized . '_'));
        $parent = dirname($normalized);
        $parent = $parent === '.' ? '' : $parent;
        \Illuminate\Support\Facades\Cache::forget('s3_browse_' . md5($parent . '_'));
        \Illuminate\Support\Facades\Cache::forget('s3_browse_' . md5(''));
    }

    /**
     * Navega y lista los contenidos (carpetas y archivos) del S3 con alto rendimiento:
     * 1. Una sola petición HTTP ListObjectsV2 usando listContents().
     * 2. Tamaño y fecha extraídos directamente de los metadatos en memoria (0 peticiones HEAD).
     * 3. Detección local de tipo MIME sin llamadas a red.
     * 4. Caché inteligente por 3 minutos con invalidación automática ante cambios.
     */
    public function browse(string $rawPath = '', ?User $user = null, ?string $search = null, bool $forceRefresh = false): array
    {
        $path = $this->normalizePath($rawPath);
        $permissions = $this->getUserPermissionsForPath($user, $path);

        if (!$permissions['can_read']) {
            throw new \Exception('No tienes permisos de lectura para esta carpeta.');
        }

        $cacheKey = 's3_browse_' . md5($path . '_' . ($search ?? ''));
        if ($forceRefresh) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        // Consultar caché para respuesta en ~5ms
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $cached['permissions'] = $permissions;
            return $cached;
        }

        $disk = $this->getDisk();
        $folders = [];
        $files = [];
        $totalSize = 0;

        // Ejecuta UNA SOLA consulta a S3 / DigitalOcean Spaces para todo el directorio
        $contents = $disk->listContents($path, false);

        foreach ($contents as $item) {
            $itemPath = $item->path();
            $itemName = basename($itemPath);

            // Filtrar archivos internos o carpetas de sistema
            if (str_starts_with($itemName, '.') || str_starts_with($itemName, '._')) {
                continue;
            }

            // Filtro por búsqueda si aplica
            if (!empty($search) && !str_contains(mb_strtolower($itemName), mb_strtolower($search))) {
                continue;
            }

            if ($item->isDir()) {
                $folders[] = [
                    'name' => $itemName,
                    'path' => $itemPath,
                    'type' => 'folder',
                ];
            } else {
                // Atributos ya en memoria retornados por ListObjectsV2
                $size = $item->fileSize() ?? 0;
                $totalSize += $size;
                $lastModified = $item->lastModified();
                $extension = strtolower(pathinfo($itemName, PATHINFO_EXTENSION));
                $mimeType = $this->guessMimeType($extension);

                $previewUrl = null;
                try {
                    // Firmado HMAC-SHA256 en memoria local, sin llamadas de red a S3
                    $previewUrl = $disk->temporaryUrl($itemPath, now()->addHours(2));
                } catch (\Throwable $e) {}

                $files[] = [
                    'name' => $itemName,
                    'path' => $itemPath,
                    'type' => 'file',
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'last_modified' => $lastModified ? date('c', $lastModified) : null,
                    'preview_url' => $previewUrl,
                ];
            }
        }

        // Ordenar carpetas y archivos alfabéticamente
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // Construir migas de pan (Breadcrumbs)
        $breadcrumbs = [
            ['name' => 'Raíz', 'path' => '']
        ];

        if ($path !== '') {
            $accum = '';
            foreach (explode('/', $path) as $seg) {
                $accum = $accum === '' ? $seg : $accum . '/' . $seg;
                $breadcrumbs[] = [
                    'name' => $seg,
                    'path' => $accum
                ];
            }
        }

        // Calcular ruta padre para botón "Atrás"
        $parentPath = null;
        if ($path !== '') {
            $lastSlash = strrpos($path, '/');
            $parentPath = $lastSlash !== false ? substr($path, 0, $lastSlash) : '';
        }

        $result = [
            'current_path' => $path,
            'parent_path' => $parentPath,
            'breadcrumbs' => $breadcrumbs,
            'folders' => $folders,
            'files' => $files,
            'total_folders' => count($folders),
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'permissions' => $permissions,
        ];

        // Almacenar en caché por 3 minutos
        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, 180);

        return $result;
    }

    /**
     * Crea una nueva carpeta en S3 / DigitalOcean Spaces.
     */
    public function createFolder(string $parentPath, string $folderName, User $user, ?string $ip = null): array
    {
        $normalizedParent = $this->normalizePath($parentPath);
        $permissions = $this->getUserPermissionsForPath($user, $normalizedParent);

        if (!$permissions['can_create_folder']) {
            throw new \Exception('No tienes permisos para crear carpetas en este directorio.');
        }

        // Limpiar nombre de carpeta
        $cleanFolderName = trim($folderName);
        $cleanFolderName = str_replace(['/', '\\', '..'], '', $cleanFolderName);

        if (empty($cleanFolderName)) {
            throw new \Exception('El nombre de la carpeta no es válido.');
        }

        $fullPath = $normalizedParent === '' ? $cleanFolderName : $normalizedParent . '/' . $cleanFolderName;

        $disk = $this->getDisk();

        // En S3 / Spaces colocamos un archivo marcador .keep para asegurar persistencia
        $disk->makeDirectory($fullPath);
        $disk->put($fullPath . '/.keep', '');

        // Auditoría
        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => 'create_folder',
            'path' => $fullPath,
            'details' => ['parent' => $normalizedParent, 'folder_name' => $cleanFolderName],
            'ip_address' => $ip,
        ]);

        // Invalidar caché del directorio
        $this->clearPathCache($normalizedParent);

        return [
            'message' => 'Carpeta creada exitosamente.',
            'name' => $cleanFolderName,
            'path' => $fullPath,
        ];
    }

    /**
     * Sube uno o varios archivos a la ruta indicada en S3 / Spaces.
     */
    public function uploadFiles(string $targetPath, array $files, User $user, ?string $ip = null): array
    {
        $normalizedPath = $this->normalizePath($targetPath);
        $permissions = $this->getUserPermissionsForPath($user, $normalizedPath);

        if (!$permissions['can_upload']) {
            throw new \Exception('No tienes permisos para subir archivos en este directorio.');
        }

        $disk = $this->getDisk();
        $uploaded = [];

        foreach ($files as $file) {
            if (!($file instanceof UploadedFile)) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $safeName = $this->sanitizeFilename($originalName);

            $filePath = $normalizedPath === '' ? $safeName : $normalizedPath . '/' . $safeName;

            // Subir al Space
            $disk->putFileAs($normalizedPath, $file, $safeName);

            // Registrar auditoría
            DocumentActivityLog::create([
                'user_id' => $user->id,
                'action' => 'upload',
                'path' => $filePath,
                'details' => [
                    'original_name' => $originalName,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ],
                'ip_address' => $ip,
            ]);

            $uploaded[] = [
                'name' => $safeName,
                'path' => $filePath,
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
                'preview_url' => $disk->temporaryUrl($filePath, now()->addHours(2)),
            ];
        }

        // Invalidar caché del directorio donde se subieron los archivos
        $this->clearPathCache($normalizedPath);

        return [
            'message' => count($uploaded) . ' archivo(s) subido(s) exitosamente.',
            'uploaded' => $uploaded,
        ];
    }

    /**
     * Elimina un archivo o una carpeta en S3 / Spaces.
     */
    public function deleteItem(string $itemPath, string $type, User $user, ?string $ip = null): array
    {
        $path = $this->normalizePath($itemPath);
        $permissions = $this->getUserPermissionsForPath($user, $path);

        if (!$permissions['can_delete']) {
            throw new \Exception('No tienes permisos para eliminar este elemento.');
        }

        $disk = $this->getDisk();

        if ($type === 'folder') {
            $disk->deleteDirectory($path);
        } else {
            $disk->delete($path);
        }

        // Auditoría
        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => 'delete',
            'path' => $path,
            'details' => ['type' => $type],
            'ip_address' => $ip,
        ]);

        // Invalidar caché del elemento y su directorio padre
        $this->clearPathCache($path);

        return [
            'message' => ($type === 'folder' ? 'Carpeta eliminada' : 'Archivo eliminado') . ' exitosamente.',
            'path' => $path,
        ];
    }

    /**
     * Renombra o mueve un archivo o carpeta.
     */
    public function renameItem(string $oldPath, string $newName, string $type, User $user, ?string $ip = null): array
    {
        $normalizedOld = $this->normalizePath($oldPath);
        $permissions = $this->getUserPermissionsForPath($user, $normalizedOld);

        if (!$permissions['can_upload'] && !$permissions['can_delete']) {
            throw new \Exception('No tienes permisos suficientes para renombrar este elemento.');
        }

        $cleanNewName = trim($newName);
        $cleanNewName = str_replace(['/', '\\'], '', $cleanNewName);

        $parent = dirname($normalizedOld);
        $parent = $parent === '.' ? '' : $parent;
        $normalizedNew = $parent === '' ? $cleanNewName : $parent . '/' . $cleanNewName;

        $disk = $this->getDisk();

        if ($disk->exists($normalizedNew)) {
            throw new \Exception('Ya existe un elemento con ese nombre en este directorio.');
        }

        $disk->move($normalizedOld, $normalizedNew);

        // Auditoría
        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => 'rename',
            'path' => $normalizedNew,
            'details' => ['from' => $normalizedOld, 'to' => $normalizedNew, 'type' => $type],
            'ip_address' => $ip,
        ]);

        // Invalidar caché de ambas rutas
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
     * Genera una URL temporal firmada para descarga directa de un archivo.
     */
    public function getDownloadUrl(string $filePath, User $user, ?string $ip = null): array
    {
        $path = $this->normalizePath($filePath);
        $permissions = $this->getUserPermissionsForPath($user, $path);

        if (!$permissions['can_read']) {
            throw new \Exception('No tienes permisos para descargar este archivo.');
        }

        $disk = $this->getDisk();

        if (!$disk->exists($path)) {
            throw new \Exception('El archivo especificado no existe.');
        }

        $url = $disk->temporaryUrl($path, now()->addMinutes(60));

        // Registrar descarga
        DocumentActivityLog::create([
            'user_id' => $user->id,
            'action' => 'download',
            'path' => $path,
            'details' => ['filename' => basename($path)],
            'ip_address' => $ip,
        ]);

        return [
            'name' => basename($path),
            'path' => $path,
            'download_url' => $url,
            'size' => $disk->size($path),
            'size_formatted' => $this->formatBytes($disk->size($path)),
        ];
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
}
