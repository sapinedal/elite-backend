<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;

/**
 * Archivos de las cuentas de cobro en S3, subidos directo al bucket con URL firmada (DirectUploadService):
 *
 *   <cuentas_cobro.s3_root>/<proyecto>/<tipo>/<código>/<cuenta de cobro, adjuntos y soporte de Multifox>
 *                                                     /01 Cargue ERP, 02 Factura, 03 Liquidacion ERP (cuentas directas)
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
        return $this->uploads->forModule('Cuentas de cobro')->claimUpload($token, $user, $folder);
    }

    /**
     * Valida el documento de un paso de la cuenta directa ya subido y lo deja en la subcarpeta del paso.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimStepFile(string $token, User $user, CuentaCobro $cuenta, string $step): array
    {
        return $this->claimFile($token, $user, $this->stepFolder($cuenta, $step));
    }

    /**
     * Copia a la subcarpeta del paso un archivo que ya está en S3 (la factura de la carpeta del mes que el residente
     * asocia a su cuenta de cobro). Devuelve la ruta de la copia.
     */
    public function copyStepFile(CuentaCobro $cuenta, string $step, string $path): string
    {
        return $this->uploads->forModule('Cuentas de cobro')->copyTo($path, $this->stepFolder($cuenta, $step));
    }

    protected function stepFolder(CuentaCobro $cuenta, string $step): string
    {
        return rtrim($cuenta->storage_path, '/') . '/' . CuentaCobroWorkflow::STEPS[$step]['folder'];
    }

    public function downloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
