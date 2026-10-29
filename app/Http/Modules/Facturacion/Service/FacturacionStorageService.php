<?php

namespace App\Http\Modules\Facturacion\Service;

use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Carbon\CarbonInterface;

/**
 * Archivos de las facturas del mes en S3, subidos directo al bucket con URL firmada (DirectUploadService):
 *
 *   <facturacion.s3_root>/OCTUBRE 2026/<facturas que carga Contabilidad>
 *                                     /Soportes/<FE 335 INVALGO SAS>/<soportes del residente>
 */
class FacturacionStorageService
{
    public const FOLDER_SUPPORTS = 'Soportes';

    protected const MONTHS = [
        1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
    ];

    public function __construct(protected DirectUploadService $uploads)
    {
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload($fileName, $fileSize, $mime, $user, (int) config('facturacion.max_upload_mb'));
    }

    /**
     * Nombre de la carpeta del mes: "OCTUBRE 2026".
     */
    public static function monthLabel(CarbonInterface $period): string
    {
        return self::MONTHS[$period->month] . ' ' . $period->year;
    }

    public function monthFolder(CarbonInterface $period): string
    {
        return trim(config('facturacion.s3_root'), '/') . '/' . self::monthLabel($period);
    }

    /**
     * Valida la factura ya subida y la deja en la carpeta del mes.
     *
     * @return array{file_name: string, file_path: string, file_size: int, file_mime: string}
     */
    public function claimInvoiceFile(string $token, User $user, CarbonInterface $period): array
    {
        return $this->uploads->claimUpload($token, $user, $this->monthFolder($period));
    }

    /**
     * Valida un soporte ya subido y lo deja en la carpeta de soportes de la factura.
     */
    public function claimSupportFile(string $token, User $user, FacturacionInvoice $invoice): array
    {
        $folder = $this->uploads->cleanSegment($invoice->documentNumber() . ' ' . $invoice->supplier_name, 100);

        return $this->uploads->claimUpload($token, $user, $this->monthFolder($invoice->period) . '/' . self::FOLDER_SUPPORTS . '/' . $folder);
    }

    public function delete(string $path): void
    {
        $this->uploads->delete($path);
    }

    public function downloadUrl(string $path, string $name): string
    {
        return $this->uploads->downloadUrl($path, $name);
    }
}
