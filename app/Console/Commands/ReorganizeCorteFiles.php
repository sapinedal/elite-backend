<?php

namespace App\Console\Commands;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Service\CorteStorageService;
use App\Services\DirectUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mueve los archivos de los cortes creados antes de la estructura por contrato
 * (tecnica/cortes/<año>/<mes>/<uuid>/...) a <carpeta del contrato>/Cortes/<código>/{01 Corte, 02 Facturas}.
 * Actualiza las rutas del corte, su historial y sus facturas. Se puede ejecutar varias veces.
 *
 * Uso: php artisan cortes:reorganizar-archivos --dry-run   (solo muestra qué movería)
 */
class ReorganizeCorteFiles extends Command
{
    protected $signature = 'cortes:reorganizar-archivos {--dry-run : Solo mostrar los cambios, sin mover nada}';

    protected $description = 'Reubica los archivos de los cortes en la carpeta de su contrato';

    public function handle(CorteStorageService $storage, DirectUploadService $uploads): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('s3');
        $rows = [];

        foreach (Corte::with('invoices')->orderBy('id')->get() as $corte) {
            try {
                $folder = $corte->storage_path
                    ?: $storage->folderFor($corte->project_id, $corte->tower_id, $corte->contract_id, $corte->code);

                $files = [[$corte, $folder . '/' . CorteStorageService::FOLDER_CORTE, 'Corte']];
                foreach ($corte->invoices as $invoice) {
                    $files[] = [$invoice, $folder . '/' . CorteStorageService::FOLDER_INVOICES, "Factura {$invoice->invoice_number}"];
                }

                foreach ($files as [$model, $target, $label]) {
                    $old = $model->file_path;
                    if (Str::startsWith($old, $target . '/')) {
                        continue; // Ya está en su carpeta
                    }
                    if (!$disk->exists($old)) {
                        $rows[] = [$corte->code, $label, $old, '(no existe en S3, se omite)'];
                        continue;
                    }

                    $new = $dry ? "{$target}/" . basename($old) : $uploads->moveTo($old, $target);
                    $rows[] = [$corte->code, $label, $old, $new];

                    if (!$dry) {
                        $model->update(['file_path' => $new]);
                        $corte->history()->where('file_path', $old)->update(['file_path' => $new]);
                    }
                }

                if (!$dry && $corte->storage_path !== $folder) {
                    $corte->update(['storage_path' => $folder]);
                }
            } catch (Throwable $e) {
                $rows[] = [$corte->code, '-', '-', 'ERROR: ' . $e->getMessage()];
            }
        }

        $rows ? $this->table(['Corte', 'Archivo', 'Ruta anterior', 'Ruta nueva'], $rows) : $this->info('No hay archivos por mover.');
        $this->info($dry ? 'Simulación: no se movió nada.' : 'Listo.');

        return self::SUCCESS;
    }
}
