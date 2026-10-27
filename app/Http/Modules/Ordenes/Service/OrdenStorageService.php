<?php

namespace App\Http\Modules\Ordenes\Service;

use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Support\Str;

/**
 * Archivos de las órdenes en S3, subidos directo al bucket con URL firmada (DirectUploadService):
 *
 *   <ordenes.s3_root>/<proyecto>/<torre>/<código> - <descripción>/01 Orden ERP/...
 *                                                                /02 Factura proveedor/...
 *                                                                /03 Entrada almacen/...
 */
class OrdenStorageService
{
    public function __construct(protected DirectUploadService $uploads)
    {
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload($fileName, $fileSize, $mime, $user, (int) config('ordenes.max_upload_mb'));
    }

    /**
     * Carpeta de la orden. Se calcula una vez (al crearla) y se guarda, así no cambia si se edita la descripción.
     */
    public function folderOf(Orden $orden): string
    {
        if (!$orden->storage_path) {
            $orden->loadMissing(['project:id,name', 'tower:id,name']);

            $orden->update(['storage_path' => implode('/', [
                trim(config('ordenes.s3_root'), '/'),
                $this->uploads->cleanSegment($orden->project?->name ?? 'SIN PROYECTO'),
                $this->uploads->cleanSegment($orden->tower?->name ?? 'GENERAL'),
                $this->uploads->cleanSegment($orden->code . ' - ' . Str::limit(Str::squish($orden->description), 60, ''), 100),
            ])]);
        }

        return $orden->storage_path;
    }

    /**
     * Valida el documento ya subido y lo deja en la subcarpeta de su paso.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimStepFile(string $token, User $user, Orden $orden, string $step): array
    {
        return $this->uploads->claimUpload($token, $user, $this->folderOf($orden) . '/' . OrdenWorkflow::STEPS[$step]['folder']);
    }

    public function downloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
