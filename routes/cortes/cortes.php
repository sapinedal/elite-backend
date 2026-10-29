<?php

use App\Http\Modules\Cortes\Controller\CorteController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Registro de Corte: radicación de cortes de obra (archivo directo a S3 con URL firmada)
 * y flujo de aprobación Interventor -> Director de Obra -> Gerencia. Las facturas llegan desde las facturas del mes
 * (routes/facturacion): Contabilidad las carga y el residente las asocia a su corte aprobado.
 * La autorización por corte (quién ve / revisa / reenvía) la resuelve CorteService.
 */
Route::prefix('cortes')->middleware('auth:sanctum')->group(function () {
    Route::get('/catalogs', [CorteController::class, 'catalogs'])->middleware('permission:cortes.ver');
    Route::post('/upload-url', [CorteController::class, 'uploadUrl'])->middleware('permission:cortes.crear');
    // Confirmación de carga en Multifox: por cargo (Paula) o permiso cortes.multifox, lo valida CorteInvoiceService
    Route::post('/invoices/{invoice}/multifox', [CorteController::class, 'confirmMultifox'])->middleware('permission:cortes.ver');
    Route::get('/invoices/{invoice}/download', [CorteController::class, 'downloadInvoice'])->middleware('permission:cortes.ver|cortes.facturar');

    Route::get('/', [CorteController::class, 'index'])->middleware('permission:cortes.ver');
    Route::post('/', [CorteController::class, 'store'])->middleware('permission:cortes.crear');
    Route::get('/{corte}', [CorteController::class, 'show'])->middleware('permission:cortes.ver');
    Route::get('/{corte}/download', [CorteController::class, 'download'])->middleware('permission:cortes.ver');
    Route::post('/{corte}/review', [CorteController::class, 'review'])
        ->middleware('permission:cortes.aprobar_interventor|cortes.aprobar_director|cortes.aprobar_gerencia');
    Route::post('/{corte}/resubmit', [CorteController::class, 'resubmit'])->middleware('permission:cortes.crear');
});
