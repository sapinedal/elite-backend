<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;

/**
 * Archivos de las cuentas de cobro en S3, subidos directo al bucket con URL firmada (DirectUploadService):
 *
 *   <cuentas_cobro.s3_root>/<proyecto>/<tipo>/<código>/<cuenta de cobro, adjuntos y soporte de Multifox>
 */
class CuentaCobroStorageService
{
    public function __construct(protected DirectUploadService $uploads)
    {
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload($fileName, $fileSize, $mime, $user, (int) config('cuentas_cobro.max_upload_mb'));
    }

    public function folderFor(Project $project, CuentaCobroTipo $tipo, string $code): string
    {
        return implode('/', [
            trim(config('cuentas_cobro.s3_root'), '/'),
            $this->uploads->cleanSegment($project->name),
            $this->uploads->cleanSegment(mb_strtoupper($tipo->name, 'UTF-8')),
            $this->uploads->cleanSegment($code, 100),
        ]);
    }

    /**
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimFile(string $token, User $user, string $folder): array
    {
        return $this->uploads->claimUpload($token, $user, $folder);
    }

    public function downloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
