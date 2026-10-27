<?php

use App\Http\Modules\Pagos\Controller\PagoController;
use Illuminate\Support\Facades\Route;

/**
 * Cuentas por pagar: cuadro de pagos alimentado por cortes (factura en Multifox), órdenes (entrada cargada)
 * y cargues manuales. Priorizar se habilita también por cargo, por eso la autorización la resuelve PagoService.
 */
Route::prefix('pagos')->middleware('auth:sanctum')->group(function () {
    Route::get('/catalogs', [PagoController::class, 'catalogs']);
    Route::get('/export', [PagoController::class, 'export']);
    Route::post('/upload-url', [PagoController::class, 'uploadUrl']);
    Route::post('/prioritize', [PagoController::class, 'prioritize']);
    Route::get('/movimientos/{movimiento}/download', [PagoController::class, 'downloadSupport']);

    Route::get('/', [PagoController::class, 'index']);
    Route::post('/', [PagoController::class, 'store'])->middleware('permission:pagos.registrar');
    Route::get('/{cuenta}', [PagoController::class, 'show']);
    Route::post('/{cuenta}/unschedule', [PagoController::class, 'unschedule']);
    Route::post('/{cuenta}/pay', [PagoController::class, 'pay'])->middleware('permission:pagos.pagar');
    Route::post('/{cuenta}/annul', [PagoController::class, 'annul'])->middleware('permission:pagos.registrar');
});
