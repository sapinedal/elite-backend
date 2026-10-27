<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;

/**
 * Archivos de los cortes en S3. Los cortes pueden pesar hasta 1 GB: se suben directo al bucket con
 * URL firmada (DirectUploadService) y al guardar se mueven a la carpeta del corte.
 *
 * Cada corte vive dentro de la carpeta de su contrato, junto al resto de documentos del contrato:
 *
 *   <carpeta del contrato>/Cortes/<código del corte>/01 Corte/<archivo del corte>
 *                                                   /02 Facturas/<facturas y notas crédito>
 *
 * Si el contrato aún no tiene carpeta se crea con la misma lógica de Contratación. Sin contrato (no debería
 * pasar: es obligatorio) se usa config('cortes.s3_root')/<proyecto>/<torre>/<código>.
 */
class CorteStorageService
{
    public const FOLDER_CORTE = '01 Corte';
    public const FOLDER_INVOICES = '02 Facturas';

    public function __construct(
        protected DirectUploadService $uploads,
        protected ContractStorageService $contracts,
    ) {
    }

    /**
     * Paso 1 de la carga: URL firmada para subir el archivo directo a S3.
     */
    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload(
            $fileName,
            $fileSize,
            $mime,
            $user,
            (int) config('cortes.max_upload_mb'),
            (int) config('cortes.upload_url_minutes')
        );
    }

    /**
     * Carpeta del corte según su contrato y código.
     */
    public function folderFor(int $projectId, ?int $towerId, ?int $contractId, string $code): string
    {
        $contract = $contractId ? ContratacionContract::find($contractId) : null;

        if ($contract && !$contract->s3_path) {
            $contract->update(['s3_path' => $this->contracts->createContractFolder($contract)]);
        }

        $base = $contract?->s3_path
            ? trim($contract->s3_path, '/')
            : implode('/', [
                trim(config('cortes.s3_root'), '/'),
                $this->uploads->cleanSegment(Project::find($projectId)?->name ?? 'SIN PROYECTO'),
                $this->uploads->cleanSegment(($towerId ? Tower::find($towerId)?->name : null) ?? 'GENERAL'),
            ]);

        return "{$base}/Cortes/" . $this->uploads->cleanSegment($code, 100);
    }

    /**
     * Carpeta guardada del corte; los cortes anteriores a esta estructura la calculan y la guardan.
     */
    public function folderOf(Corte $corte): string
    {
        if (!$corte->storage_path) {
            $corte->update(['storage_path' => $this->folderFor($corte->project_id, $corte->tower_id, $corte->contract_id, $corte->code)]);
        }

        return $corte->storage_path;
    }

    /**
     * Valida el archivo del corte ya subido y lo deja en <carpeta>/01 Corte.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimCorteFile(string $token, User $user, string $folder): array
    {
        return $this->uploads->claimUpload($token, $user, $folder . '/' . self::FOLDER_CORTE);
    }

    /**
     * Valida el archivo de una factura ya subido y lo deja en <carpeta del corte>/02 Facturas.
     */
    public function claimInvoiceFile(string $token, User $user, Corte $corte): array
    {
        return $this->uploads->claimUpload($token, $user, $this->folderOf($corte) . '/' . self::FOLDER_INVOICES);
    }

    public function downloadUrl(Corte $corte): string
    {
        return $this->fileDownloadUrl($corte->file_path, $corte->file_name);
    }

    /**
     * URL temporal (60 min) para descargar un archivo de cortes o facturas con su nombre original.
     */
    public function fileDownloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
