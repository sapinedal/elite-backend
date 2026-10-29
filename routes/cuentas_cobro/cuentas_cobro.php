<?php

use App\Http\Modules\CuentasCobro\Controller\CuentaCobroController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Cuentas de cobro: radicación (con adjuntos según el tipo), aprobación Director de Obra -> Gerencia,
 * decisión de Paula sobre Multifox y paso al cuadro de pagos. Incluye el CRUD de tipos (cuentas_cobro.parametrizar).
 * La autorización por cuenta (quién ve / aprueba / reenvía / gestiona Multifox) la resuelve CuentaCobroService.
 */
Route::prefix('cuentas-cobro')->middleware('auth:sanctum')->group(function () {
    Route::get('/catalogs', [CuentaCobroController::class, 'catalogs'])->middleware('permission:cuentas_cobro.ver');
    Route::post('/upload-url', [CuentaCobroController::class, 'uploadUrl'])->middleware('permission:cuentas_cobro.ver');

    Route::get('/tipos', [CuentaCobroController::class, 'tipos'])->middleware('permission:cuentas_cobro.parametrizar');
    Route::post('/tipos', [CuentaCobroController::class, 'storeTipo'])->middleware('permission:cuentas_cobro.parametrizar');
    Route::put('/tipos/{tipo}', [CuentaCobroController::class, 'updateTipo'])->middleware('permission:cuentas_cobro.parametrizar');
    Route::delete('/tipos/{tipo}', [CuentaCobroController::class, 'destroyTipo'])->middleware('permission:cuentas_cobro.parametrizar');

    Route::get('/', [CuentaCobroController::class, 'index'])->middleware('permission:cuentas_cobro.ver');
    Route::post('/', [CuentaCobroController::class, 'store'])->middleware('permission:cuentas_cobro.crear');
    Route::get('/{cuenta}', [CuentaCobroController::class, 'show'])->middleware('permission:cuentas_cobro.ver');
    Route::get('/{cuenta}/download', [CuentaCobroController::class, 'download'])->middleware('permission:cuentas_cobro.ver');
    Route::post('/{cuenta}/review', [CuentaCobroController::class, 'review'])
        ->middleware('permission:cuentas_cobro.aprobar_director|cuentas_cobro.aprobar_gerencia');
    Route::post('/{cuenta}/resubmit', [CuentaCobroController::class, 'resubmit'])->middleware('permission:cuentas_cobro.crear');
    // Paula (cargo de Multifox o permiso cortes.multifox): lo valida CuentaCobroService
    Route::post('/{cuenta}/multifox-decision', [CuentaCobroController::class, 'decideMultifox'])->middleware('permission:cuentas_cobro.ver');
    Route::post('/{cuenta}/multifox', [CuentaCobroController::class, 'registerMultifox'])->middleware('permission:cuentas_cobro.ver');
});
