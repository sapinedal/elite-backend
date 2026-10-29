<?php

namespace App\Http\Modules\Anticipos\Service;

use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;

/**
 * Archivos de los anticipos en S3, subidos directo al bucket con URL firmada (DirectUploadService).
 * Cada anticipo vive en la carpeta de su contrato, junto al resto de documentos del contrato:
 *
 *   tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/8. EMELECT - M.O Fundación/08 ANTICIPOS/ANT-0003/
 *       <cuenta de cobro del residente>
 *       <soporte del anticipo generado en Multifox>
 */
class AnticipoStorageService
{
    public function __construct(
        protected DirectUploadService $uploads,
        protected ContractStorageService $contracts,
    ) {
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload($fileName, $fileSize, $mime, $user, (int) config('anticipos.max_upload_mb'));
    }

    /**
     * Carpeta del anticipo; si el contrato aún no tiene carpeta se crea con la misma lógica de Contratación.
     */
    public function folderFor(ContratacionContract $contract, string $code): string
    {
        if (!$contract->s3_path) {
            $contract->update(['s3_path' => $this->contracts->createContractFolder($contract)]);
        }

        return trim($contract->s3_path, '/') . '/' . trim(config('anticipos.folder'), '/') . '/' . $this->uploads->cleanSegment($code, 100);
    }

    /**
     * Valida el archivo ya subido y lo deja en la carpeta del anticipo.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimFile(string $token, User $user, string $folder): array
    {
        return $this->uploads->claimUpload($token, $user, $folder);
    }

    /**
     * Mueve la cuenta de cobro a otra carpeta (al reenviar el anticipo con otro contrato) y devuelve la ruta.
     */
    public function moveTo(string $path, string $folder): string
    {
        return $this->uploads->moveTo($path, $folder);
    }

    public function downloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
