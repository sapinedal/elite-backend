<?php

use App\Http\Modules\Facturacion\Controller\FacturacionController;
use Illuminate\Support\Facades\Route;

/**
 * Facturas del mes: Contabilidad (cortes.facturar) carga las facturas en la carpeta del mes y ve el panel;
 * el residente (cortes.crear / ordenes.crear / cuentas_cobro.crear) las asocia a su corte aprobado, a su orden o a su
 * cuenta de cobro directa. Los indirectos los aprueba el analista contable (facturacion.aprobar_indirecto) con su valor.
 * La autorización por factura (quién ve / edita / asocia) la resuelve FacturacionService.
 */
Route::prefix('facturacion')->middleware('auth:sanctum')->group(function () {
    $all = 'permission:cortes.facturar|cortes.crear|cortes.eliminar|ordenes.crear|cuentas_cobro.crear|anticipos.crear|facturacion.aprobar_indirecto';
    $manage = 'permission:cortes.facturar';
    // cortes.eliminar: quien corrige una factura mal cargada vuelve a asociar la nueva al corte
    $associate = 'permission:cortes.crear|cortes.eliminar|ordenes.crear|cuentas_cobro.crear|anticipos.crear';

    Route::get('/catalogs', [FacturacionController::class, 'catalogs'])->middleware($all);
    Route::post('/upload-url', [FacturacionController::class, 'uploadUrl'])->middleware($all);
    Route::get('/panel', [FacturacionController::class, 'panel'])->middleware($manage);
    Route::get('/targets', [FacturacionController::class, 'targets'])->middleware($associate);
    Route::get('/supports/{support}/download', [FacturacionController::class, 'downloadSupport'])->middleware($all);

    Route::get('/', [FacturacionController::class, 'index'])->middleware($all);
    Route::post('/', [FacturacionController::class, 'store'])->middleware($manage);
    Route::get('/{invoice}', [FacturacionController::class, 'show'])->middleware($all);
    Route::put('/{invoice}', [FacturacionController::class, 'update'])->middleware($manage);
    // Pendientes: Contabilidad (cortes.facturar); procesadas: facturacion.eliminar (lo valida RegistroDeletionService)
    Route::delete('/{invoice}', [FacturacionController::class, 'destroy'])->middleware('permission:cortes.facturar|facturacion.eliminar');
    Route::get('/{invoice}/download', [FacturacionController::class, 'download'])->middleware($all);
    // Analista contable: registra el valor del indirecto y lo pasa a cuentas por pagar
    Route::post('/{invoice}/approve-indirecto', [FacturacionController::class, 'approveIndirecto'])->middleware('permission:facturacion.aprobar_indirecto');
    Route::post('/{invoice}/associate', [FacturacionController::class, 'associate'])->middleware($associate);
});
