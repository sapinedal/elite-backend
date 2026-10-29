<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Service\ContractDocumentCatalog;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;

/**
 * Archivos de los cortes en S3. Los cortes pueden pesar hasta 1 GB: se suben directo al bucket con
 * URL firmada (DirectUploadService) y al guardar se mueven a la carpeta del corte.
 *
 * Cada corte vive en la subcarpeta 05 CORTES de su contrato, junto al resto de documentos del contrato.
 * La carpeta del contrato ya es una por contrato (<consecutivo>. <contratista>), aunque el proveedor se repita:
 *
 *   tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/8. EMELECT - M.O Fundación/05 CORTES/CORTE 1/<archivo>
 *                                                                                                          /<facturas asociadas>
 *
 * Los cortes anteriores conservan su carpeta guardada (<contrato>/Cortes/<código>/{01 Corte, 02 Facturas}).
 *
 * Si el contrato aún no tiene carpeta se crea con la misma lógica de Contratación. Sin contrato (no debería
 * pasar: es obligatorio) se usa config('cortes.s3_root')/<proyecto>/<torre>/<código>.
 */
class CorteStorageService
{
    // Subcarpetas de la estructura anterior (solo las usa cortes:reorganizar-archivos)
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

        $cortesFolder = $contract?->s3_path ? ContractDocumentCatalog::FOLDERS['cortes'] : 'Cortes';

        return "{$base}/{$cortesFolder}/" . $this->uploads->cleanSegment($code, 100);
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
     * Valida el archivo del corte ya subido y lo deja en la carpeta del corte.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimCorteFile(string $token, User $user, string $folder): array
    {
        return $this->uploads->claimUpload($token, $user, $folder);
    }

    /**
     * Copia a la carpeta del corte una factura que ya está en la carpeta del mes de Contabilidad: así queda en
     * ambos lados (Contabilidad la ve en su mes y obra dentro del corte). Devuelve la ruta de la copia.
     */
    public function copyInvoiceFile(Corte $corte, string $path): string
    {
        return $this->uploads->copyTo($path, $this->folderOf($corte));
    }

    /**
     * Mueve el archivo vigente del corte a otra carpeta (al reenviarlo con otro contrato o número) y devuelve la ruta.
     */
    public function moveCorteFile(Corte $corte, string $folder): string
    {
        return $this->uploads->moveTo($corte->file_path, $folder);
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
