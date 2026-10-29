<?php

use App\Http\Modules\Facturacion\Controller\FacturacionController;
use Illuminate\Support\Facades\Route;

/**
 * Facturas del mes: Contabilidad (cortes.facturar) carga las facturas en la carpeta del mes y ve el panel;
 * el residente (cortes.crear / ordenes.crear) las asocia a su corte aprobado o a su orden con los soportes.
 * La autorización por factura (quién ve / edita / asocia) la resuelve FacturacionService.
 */
Route::prefix('facturacion')->middleware('auth:sanctum')->group(function () {
    $all = 'permission:cortes.facturar|cortes.crear|ordenes.crear';
    $manage = 'permission:cortes.facturar';
    $associate = 'permission:cortes.crear|ordenes.crear';

    Route::get('/catalogs', [FacturacionController::class, 'catalogs'])->middleware($all);
    Route::post('/upload-url', [FacturacionController::class, 'uploadUrl'])->middleware($all);
    Route::get('/panel', [FacturacionController::class, 'panel'])->middleware($manage);
    Route::get('/targets', [FacturacionController::class, 'targets'])->middleware($associate);
    Route::get('/supports/{support}/download', [FacturacionController::class, 'downloadSupport'])->middleware($all);

    Route::get('/', [FacturacionController::class, 'index'])->middleware($all);
    Route::post('/', [FacturacionController::class, 'store'])->middleware($manage);
    Route::get('/{invoice}', [FacturacionController::class, 'show'])->middleware($all);
    Route::put('/{invoice}', [FacturacionController::class, 'update'])->middleware($manage);
    Route::delete('/{invoice}', [FacturacionController::class, 'destroy'])->middleware($manage);
    Route::get('/{invoice}/download', [FacturacionController::class, 'download'])->middleware($all);
    Route::post('/{invoice}/associate', [FacturacionController::class, 'associate'])->middleware($associate);
});
