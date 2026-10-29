<?php

use App\Http\Modules\Anticipos\Controller\AnticipoController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Anticipos: el residente radica la cuenta de cobro del anticipo de un contrato, la aprueban
 * Director de Obra -> Gerencia, Contabilidad la genera en Multifox y pasa al cuadro de pagos.
 * La autorización por anticipo (quién ve / aprueba / reenvía / contabiliza) la resuelve AnticipoService.
 */
Route::prefix('anticipos')->middleware('auth:sanctum')->group(function () {
    Route::get('/catalogs', [AnticipoController::class, 'catalogs'])->middleware('permission:anticipos.ver');
    Route::post('/upload-url', [AnticipoController::class, 'uploadUrl'])->middleware('permission:anticipos.crear|anticipos.contabilizar');

    Route::get('/', [AnticipoController::class, 'index'])->middleware('permission:anticipos.ver');
    Route::post('/', [AnticipoController::class, 'store'])->middleware('permission:anticipos.crear');
    Route::get('/{anticipo}', [AnticipoController::class, 'show'])->middleware('permission:anticipos.ver');
    Route::get('/{anticipo}/download', [AnticipoController::class, 'download'])->middleware('permission:anticipos.ver');
    Route::post('/{anticipo}/review', [AnticipoController::class, 'review'])
        ->middleware('permission:anticipos.aprobar_director|anticipos.aprobar_gerencia');
    Route::post('/{anticipo}/resubmit', [AnticipoController::class, 'resubmit'])->middleware('permission:anticipos.crear');
    Route::post('/{anticipo}/multifox', [AnticipoController::class, 'registerMultifox'])->middleware('permission:anticipos.contabilizar');
});
