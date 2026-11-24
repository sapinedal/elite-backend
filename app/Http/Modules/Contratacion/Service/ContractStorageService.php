<?php

namespace App\Http\Modules\Contratacion\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionContractFolder;
use App\Http\Modules\Contratacion\Models\ContratacionDocument;
use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Carpetas y archivos de los contratos en S3. Reutiliza el disco y la caché del módulo
 * Documental para que lo creado aquí se vea de inmediato en el explorador.
 *
 * Ruta: <s3_root>/<carpeta del proyecto>/<FRENTE>/<TIPO>/<consecutivo>. <contratista> - <objeto>/<subcarpeta>
 * ej. tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape/03 CONTRATO
 */
class ContractStorageService
{
    public function __construct(
        protected DocumentalService $documental,
        protected DocumentalSettingsService $settings,
    ) {
    }

    /**
     * Número y nombre de la carpeta de cada tipo de contrato dentro de un frente, según la estructura
     * de Contratos San Miguel. Se usa solo cuando el frente aún no tiene carpeta para ese tipo.
     * Clave: nombre comparable del tipo (ver comparable()).
     */
    protected const TYPE_FOLDERS = [
        'estudios y disenos' => '1. CONTRATOS DE ESTUDIOS Y DISEÑOS',
        'urbanismo' => '2. CONTRATOS DE URBANISMO',
        'prestacion de servicios' => '4. CONTRATOS DE PRESTACIÓN DE SERVICIOS',
        'servicio' => '5. ORDENES DE SERVICIO',
        'obligaciones urbanisticas' => '6. OBLIGACIONES URBANÍSTICAS',
        'mano de obra' => '7. CONTRATOS DE MANO DE OBRA',
        'suministro' => '8. CONTRATOS DE SUMINISTRO',
        'suministro e instalacion' => '10. CONTRATOS DE SUMINISTRO E INSTALACIÓN',
        'transporte' => '11. CONTRATOS DE TRANSPORTE',
    ];

    protected function disk(): Filesystem
    {
        return $this->documental->getDisk();
    }

    /**
     * Crea la carpeta del contrato con sus subcarpetas y devuelve la ruta.
     * Reutiliza las carpetas de frente y tipo que ya existan (ej. "8. CONTRATOS DE SUMINISTRO") y, si el
     * contrato ya tiene carpeta (mismo consecutivo y contratista, ej. "04. YANCELLY MONSALVE - M.O enchape"),
     * la devuelve tal cual sin crear nada: sus archivos se registran con syncDocuments().
     */
    public function createContractFolder(ContratacionContract $contract): string
    {
        $contract->loadMissing(['project', 'tower', 'contractType']);

        $base = $this->projectPath($contract->project);
        $frontPath = $this->resolveFolder($base, $contract->tower?->name ?? 'GENERAL', false);
        $typePath = $this->resolveFolder($frontPath, $contract->contractType?->name ?? 'OTROS CONTRATOS', true);

        $existing = $this->findContractFolder($typePath, $contract);
        if ($existing !== null) {
            return $existing;
        }

        $contractPath = $this->joinPath($typePath, $this->contractFolderName($contract));

        $disk = $this->disk();
        foreach (ContractDocumentCatalog::FOLDERS as $folderName) {
            $folderPath = $this->joinPath($contractPath, $folderName);
            if (!$disk->directoryExists($folderPath)) {
                // S3 no tiene carpetas reales: el marcador .keep hace visible la carpeta vacía
                $disk->put($folderPath . '/.keep', '');
            }
        }

        $this->documental->clearPathCache($contractPath);
        $this->documental->clearPathCache($typePath);

        return $contractPath;
    }

    /**
     * Sube archivos a una subcarpeta del contrato y los registra.
     *
     * @param UploadedFile[] $files
     * @return ContratacionDocument[]
     */
    public function uploadDocuments(ContratacionContract $contract, string $folder, ?string $documentType, array $files, User $user): array
    {
        if (!$contract->s3_path) {
            $contract->update(['s3_path' => $this->createContractFolder($contract)]);
            $this->syncDocuments($contract);
        }

        // Se validan todos antes de subir para no dejar cargas a medias
        foreach ($files as $file) {
            $reason = $this->settings->validateUpload($file->getClientOriginalName(), (int) $file->getSize());
            if ($reason !== null) {
                throw new InvalidArgumentException($reason);
            }
        }

        $directory = $contract->folderLinks()->where('folder', $folder)->value('path')
            ?? $this->subfolderPath($contract->s3_path, $folder);
        $disk = $this->disk();
        $created = [];

        foreach ($files as $file) {
            $filename = $this->uniqueFilename($directory, $this->cleanSegment($file->getClientOriginalName()));
            $disk->putFileAs($directory, $file, $filename);

            $created[] = ContratacionDocument::create([
                'contract_id' => $contract->id,
                'folder' => $folder,
                'document_type' => $folder === ContractDocumentCatalog::FOLDER_LEGAL ? $documentType : null,
                'name' => $filename,
                'path' => $this->joinPath($directory, $filename),
                'size' => (int) $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $user->id,
            ]);
        }

        $this->documental->clearPathCache($directory);

        return $created;
    }

    public function downloadUrl(ContratacionDocument $document): string
    {
        $disk = $this->disk();
        if (!$disk->exists($document->path)) {
            throw new InvalidArgumentException('El archivo ya no existe en el repositorio documental.');
        }

        return $disk->temporaryUrl($document->path, now()->addMinutes(60), [
            'ResponseContentDisposition' => "attachment; filename*=UTF-8''" . rawurlencode($document->name),
        ]);
    }

    public function deleteDocument(ContratacionDocument $document): void
    {
        // Una carpeta vinculada puede compartirse entre contratos: borrar el archivo lo quitaría de todos
        $shared = ContratacionDocument::where('path', $document->path)->where('id', '!=', $document->id)->exists();
        if ($shared) {
            throw new InvalidArgumentException('El archivo también está asociado a otro contrato (carpeta compartida); elimínalo desde el gestor documental si ya no se usa.');
        }

        $disk = $this->disk();
        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }
        $document->delete();

        $this->documental->clearPathCache(dirname($document->path));
    }

    /**
     * Registra en contratacion_documents los archivos que hay en la carpeta del contrato y quita los registros
     * de archivos que ya no están (solo en la BD: nunca borra nada del bucket). Devuelve cuántos archivos hay.
     */
    public function syncDocuments(ContratacionContract $contract): int
    {
        // [ruta del archivo => [item, clave de la carpeta del catálogo]]
        $files = [];
        $links = $contract->folderLinks()->pluck('path', 'folder')->all();

        if ($contract->s3_path) {
            foreach ($this->listFiles($contract->s3_path) as $path => $item) {
                $relative = ltrim(Str::after($path, $contract->s3_path), '/');
                $folder = str_contains($relative, '/') ? ($this->classifySubfolder(Str::before($relative, '/')) ?? 'otros') : 'otros';
                // Un tipo con carpeta vinculada toma sus archivos solo de esa carpeta
                if (!isset($links[$folder])) {
                    $files[$path] = [$item, $folder];
                }
            }
        }
        foreach ($links as $folder => $linkPath) {
            foreach ($this->listFiles($linkPath) as $path => $item) {
                $files[$path] = [$item, $folder];
            }
        }

        $contract->documents()->whereNotIn('path', array_keys($files) ?: [''])->delete();
        $existing = $contract->documents()->get(['id', 'path', 'folder'])->keyBy('path');

        foreach ($files as $path => [$item, $folder]) {
            if ($existing->has($path)) {
                // El archivo pasó a otro tipo al cambiar los vínculos
                $document = $existing[$path];
                if ($document->folder !== $folder) {
                    $document->update([
                        'folder' => $folder,
                        'document_type' => $folder === ContractDocumentCatalog::FOLDER_LEGAL ? $this->legalTypeFor(basename($path)) : null,
                    ]);
                }
                continue;
            }

            ContratacionDocument::create([
                'contract_id' => $contract->id,
                'folder' => $folder,
                'document_type' => $folder === ContractDocumentCatalog::FOLDER_LEGAL ? $this->legalTypeFor(basename($path)) : null,
                'name' => basename($path),
                'path' => $path,
                'size' => (int) ($item->fileSize() ?? 0),
                'mime_type' => $item->mimeType(),
                'uploaded_by' => null,
            ]);
        }

        return count($files);
    }

    /**
     * Archivos de una carpeta y sus subcarpetas, sin los marcadores .keep. Se lista en lugar de usar
     * directoryExists(): en DigitalOcean Spaces devuelve false para carpetas sin marcador aunque tengan archivos.
     *
     * @return array<string, \League\Flysystem\FileAttributes>
     */
    protected function listFiles(string $directory): array
    {
        $files = [];
        foreach ($this->disk()->listContents($directory, true) as $item) {
            if ($item->isFile() && basename($item->path()) !== '.keep') {
                $files[$item->path()] = $item;
            }
        }

        return $files;
    }

    /**
     * Subcarpetas de una carpeta del gestor documental para elegir una existente desde Contratación.
     * Sin ruta parte de la carpeta de contratos del proyecto. Solo se navega dentro de <s3_root> y se ocultan
     * las carpetas asignadas a otro proyecto en el gestor documental.
     */
    public function browseFolders(Project $project, ?string $path): array
    {
        $root = trim((string) config('contratacion.s3_root'), '/');
        $path = $path === null || trim($path) === '' ? $this->projectPath($project) : $this->allowedPath($project, $path);

        $folders = collect($this->disk()->directories($path))
            ->filter(fn (string $dir) => $this->belongsToProject($project, $dir))
            ->map(fn (string $dir) => ['name' => basename($dir), 'path' => $dir])
            ->sortBy(fn ($folder) => Str::lower(Str::ascii($folder['name'])), SORT_NATURAL)
            ->values()
            ->all();

        return [
            'path' => $path,
            'root' => $root,
            'parent' => $path === $root ? null : dirname($path),
            'folders' => $folders,
            'files_count' => count($this->disk()->files($path)),
        ];
    }

    /**
     * Cambia la carpeta principal del contrato por una existente y vuelve a registrar sus archivos.
     * No mueve ni borra nada en S3: los archivos de la carpeta anterior quedan donde estaban.
     */
    public function setContractFolder(ContratacionContract $contract, string $path): int
    {
        $contract->loadMissing('project');
        $path = $this->allowedPath($contract->project, $path, true);

        $contract->update(['s3_path' => $path]);
        $this->documental->clearPathCache($path);

        return $this->syncDocuments($contract);
    }

    /**
     * Vincula un tipo de documento (ej. legal) a una carpeta existente, o quita el vínculo con $path = null.
     * Las cargas de ese tipo van a la carpeta vinculada; sin vínculo vuelven a la subcarpeta del contrato.
     */
    public function setFolderLink(ContratacionContract $contract, string $folder, ?string $path, User $user): int
    {
        if (!array_key_exists($folder, ContractDocumentCatalog::FOLDERS)) {
            throw new InvalidArgumentException('El tipo de documento indicado no existe.');
        }

        if ($path === null || trim($path) === '') {
            $contract->folderLinks()->where('folder', $folder)->delete();
        } else {
            $contract->loadMissing('project');
            ContratacionContractFolder::updateOrCreate(
                ['contract_id' => $contract->id, 'folder' => $folder],
                ['path' => $this->allowedPath($contract->project, $path, true), 'linked_by' => $user->id]
            );
        }

        return $this->syncDocuments($contract);
    }

    /**
     * Ruta normalizada dentro de <s3_root>, visible para el proyecto y (con $mustExist) existente.
     */
    protected function allowedPath(Project $project, string $path, bool $mustExist = false): string
    {
        $root = trim((string) config('contratacion.s3_root'), '/');
        $path = $this->documental->normalizePath($path);

        if ($path === '' || ($path !== $root && !str_starts_with($path, $root . '/'))) {
            throw new InvalidArgumentException("Solo se pueden usar carpetas dentro de \"{$root}\".");
        }
        if (!$this->belongsToProject($project, $path)) {
            throw new InvalidArgumentException('La carpeta pertenece a otro proyecto en el gestor documental.');
        }
        if ($mustExist && $path !== $root && !in_array($path, $this->disk()->directories(dirname($path)), true)) {
            throw new InvalidArgumentException('La carpeta indicada no existe en el repositorio documental.');
        }

        return $path;
    }

    /**
     * La carpeta es transversal o está asignada al proyecto del contrato en el gestor documental.
     */
    protected function belongsToProject(Project $project, string $path): bool
    {
        $owner = $this->documental->projectOwnerOf($path);

        return $owner === null || $owner === (int) $project->id;
    }

    /**
     * Carpeta existente del contrato dentro de la de su tipo: mismo consecutivo al inicio ("04.", "06 -")
     * y el nombre del contratista ("YANCELLY" o "YANCELY"). null si no hay o si hay más de una candidata.
     */
    protected function findContractFolder(string $typePath, ContratacionContract $contract): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $contract->consecutive);
        $keys = $this->contractorKeys((string) $contract->contractor_name);
        if ($digits === '' || !$keys) {
            return null;
        }
        $consecutive = ltrim($digits, '0');

        $matches = collect($this->disk()->directories($typePath))->filter(function (string $directory) use ($consecutive, $keys) {
            $name = basename($directory);
            if (!preg_match('/^\s*0*(\d+)\s*[.\-_ ]/', $name, $m) || $m[1] !== ($consecutive === '' ? '0' : $consecutive)) {
                return false;
            }
            $words = explode(' ', $this->comparable($name));

            return collect($keys)->contains(fn ($key) => collect($words)->contains(fn ($word) => $this->sameWord($word, $key)));
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Palabras que identifican al contratista, sin sufijos societarios ni palabras cortas.
     */
    protected function contractorKeys(string $name): array
    {
        $words = explode(' ', $this->comparable($name));

        return array_values(array_filter($words, fn ($w) => strlen($w) >= 3 && !in_array($w, ['sas', 'ltda', 'group', 'grupo', 'y', 'de', 'del', 'los', 'las'], true)));
    }

    /**
     * Iguales o con una letra de diferencia (YANCELLY / YANCELY, EMELECT / EMELEC).
     */
    protected function sameWord(string $a, string $b): bool
    {
        return $a === $b || (min(strlen($a), strlen($b)) >= 5 && levenshtein($a, $b) <= 1);
    }

    /**
     * "<consecutivo>. <contratista> - <objeto>", como las carpetas existentes.
     */
    protected function contractFolderName(ContratacionContract $contract): string
    {
        $name = trim("{$contract->consecutive}. {$contract->contractor_name}");
        $object = trim((string) $contract->object);

        return $this->cleanSegment($object !== '' ? "{$name} - {$object}" : $name);
    }

    /**
     * Subcarpeta del contrato para una clave del catálogo: reutiliza la existente aunque tenga otra
     * numeración ("01. DOCUMENTACIÓN LEGAL", "05.  CORTES DE OBRA") y si no hay, usa el nombre del catálogo.
     */
    protected function subfolderPath(string $contractPath, string $folder): string
    {
        foreach ($this->disk()->directories($contractPath) as $directory) {
            if ($this->classifySubfolder(basename($directory)) === $folder) {
                return $directory;
            }
        }

        return $this->joinPath($contractPath, ContractDocumentCatalog::FOLDERS[$folder]);
    }

    /**
     * Clave del catálogo de una subcarpeta según su nombre (ej. "05 CORTES" -> cortes). null si no encaja.
     */
    protected function classifySubfolder(string $name): ?string
    {
        $value = Str::lower(Str::ascii($name));

        return match (true) {
            str_contains($value, 'legal') => 'legal',
            str_contains($value, 'pliego') => 'pliego',
            str_contains($value, 'acta') => 'acta_inicio',
            str_contains($value, 'corte') => 'cortes',
            str_contains($value, 'liquidacion') => 'liquidacion',
            str_contains($value, 'contrato') => 'contrato',
            str_contains($value, 'otros') => 'otros',
            default => null,
        };
    }

    /**
     * Tipo de documento legal según el nombre (ej. "1.2 RUT EMELECT.pdf"). null si no se reconoce.
     */
    protected function legalTypeFor(string $filename): ?string
    {
        $name = ' ' . preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($filename))) . ' ';

        return match (true) {
            str_contains($name, ' rut ') => 'rut',
            str_contains($name, ' camara ') => 'camara_comercio',
            str_contains($name, ' cedula ') || str_contains($name, ' cc ') => 'cc',
            default => null,
        };
    }

    /**
     * Carpeta de contratos del proyecto: <s3_root>/<contracts_folder> o <s3_root>/Contratos <nombre del proyecto>
     * (ej. tecnica/Contratos San Miguel, tecnica/Contratos Caona). Si ya existe con otras mayúsculas o tildes
     * ("Contratos CAONA") se reutiliza esa, porque en S3 serían dos carpetas distintas.
     */
    protected function projectPath(Project $project): string
    {
        $root = trim((string) config('contratacion.s3_root'), '/');
        $folder = $this->projectFolder($project);
        $target = Str::lower(Str::ascii($folder));

        foreach ($this->disk()->directories($root) as $directory) {
            if (Str::lower(Str::ascii(trim(basename($directory)))) === $target) {
                return $directory;
            }
        }

        return $this->joinPath($root, $folder);
    }

    protected function projectFolder(Project $project): string
    {
        return $this->cleanSegment($project->contracts_folder ?: 'Contratos ' . $project->name);
    }

    /**
     * Busca dentro de $parent una carpeta equivalente a $name, ignorando la numeración inicial,
     * tildes, mayúsculas y prefijos como "CONTRATOS DE"; si no existe, la crea en mayúsculas.
     * Con $fuzzy también acepta coincidencias parciales (solo para tipos: en frentes "TORRE 1" no debe caer en "TORRE 10").
     */
    protected function resolveFolder(string $parent, string $name, bool $fuzzy): string
    {
        $target = $this->comparable($name);
        $existing = $this->disk()->directories($parent);

        foreach ($existing as $directory) {
            if ($this->comparable(basename($directory)) === $target) {
                return $directory;
            }
        }
        foreach ($fuzzy ? $existing : [] as $directory) {
            $candidate = $this->comparable(basename($directory));
            // "Suministro" no debe caer en "Suministro e Instalación" ni al revés
            if (str_contains($candidate, 'instalacion') !== str_contains($target, 'instalacion')) {
                continue;
            }
            if ($candidate !== '' && (str_contains($candidate, $target) || str_contains($target, $candidate))) {
                return $directory;
            }
        }

        // Tipo nuevo en el frente: se crea con la numeración de la estructura (ej. "7. CONTRATOS DE MANO DE OBRA")
        $folderName = $fuzzy && isset(self::TYPE_FOLDERS[$target]) ? self::TYPE_FOLDERS[$target] : mb_strtoupper($name, 'UTF-8');

        return $this->joinPath($parent, $this->cleanSegment($folderName));
    }

    protected function comparable(string $name): string
    {
        $value = Str::lower(Str::ascii($name));
        $value = preg_replace('/^[\d\s.\-_]+/', '', $value);
        $value = preg_replace('/^(contratos?|ordenes?)\s+(de\s+)?/', '', $value);
        $value = preg_replace('/\bsum\b/', 'suministro', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }

    protected function uniqueFilename(string $directory, string $filename): string
    {
        $disk = $this->disk();
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

    /**
     * Limpia un segmento de ruta (carpeta o archivo) de caracteres problemáticos en S3 / URLs.
     */
    protected function cleanSegment(string $value): string
    {
        $clean = trim(str_replace(['/', '\\', '..', '#', '?', '%', '&'], '-', $value));
        $clean = ltrim($clean, '.');

        if ($clean === '') {
            throw new InvalidArgumentException('El nombre indicado no es válido.');
        }

        return Str::limit($clean, 150, '');
    }

    protected function joinPath(string $directory, string $name): string
    {
        return $directory === '' ? $name : rtrim($directory, '/') . '/' . $name;
    }
}
