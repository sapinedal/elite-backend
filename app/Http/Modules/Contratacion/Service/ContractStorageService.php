<?php

namespace App\Http\Modules\Contratacion\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
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
 * Ruta: <s3_root>/<carpeta del proyecto>/<FRENTE>/<TIPO>/<consecutivo>. <contratista>/<subcarpeta>
 */
class ContractStorageService
{
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
     * Crea la carpeta del contrato con sus subcarpetas y devuelve la ruta.
     * Reutiliza las carpetas de frente y tipo que ya existan (ej. "8. CONTRATOS DE SUMINISTRO").
     */
    public function createContractFolder(ContratacionContract $contract): string
    {
        $contract->loadMissing(['project', 'tower', 'contractType']);

        $base = $this->joinPath(config('contratacion.s3_root'), $this->projectFolder($contract->project));
        $frontPath = $this->resolveFolder($base, $contract->tower?->name ?? 'GENERAL', false);
        $typePath = $this->resolveFolder($frontPath, $contract->contractType?->name ?? 'OTROS CONTRATOS', true);

        $contractFolder = $this->cleanSegment("{$contract->consecutive}. {$contract->contractor_name}");
        $contractPath = $this->joinPath($typePath, $contractFolder);

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
        }

        // Se validan todos antes de subir para no dejar cargas a medias
        foreach ($files as $file) {
            $reason = $this->settings->validateUpload($file->getClientOriginalName(), (int) $file->getSize());
            if ($reason !== null) {
                throw new InvalidArgumentException($reason);
            }
        }

        $directory = $this->joinPath($contract->s3_path, ContractDocumentCatalog::FOLDERS[$folder]);
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
        $disk = $this->disk();
        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }
        $document->delete();

        $this->documental->clearPathCache(dirname($document->path));
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
            if ($candidate !== '' && (str_contains($candidate, $target) || str_contains($target, $candidate))) {
                return $directory;
            }
        }

        return $this->joinPath($parent, $this->cleanSegment(mb_strtoupper($name, 'UTF-8')));
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
