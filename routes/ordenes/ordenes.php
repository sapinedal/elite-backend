<?php

use App\Http\Modules\Ordenes\Controller\OrdenController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Registro de Orden: registro de órdenes, aprobación Director de Obra -> Gerencia y cargue de
 * documentos (orden de compra ERP, factura del proveedor, entrada de almacén) directo a S3.
 * La autorización por orden (quién ve / aprueba / carga) la resuelve OrdenService; los pasos de Almacén
 * se habilitan también por cargo, por eso esas rutas solo exigen ordenes.ver.
 */
Route::prefix('ordenes')->middleware('auth:sanctum')->group(function () {
    Route::get('/catalogs', [OrdenController::class, 'catalogs'])->middleware('permission:ordenes.ver');
    Route::post('/upload-url', [OrdenController::class, 'uploadUrl'])->middleware('permission:ordenes.ver');
    Route::get('/documents/{document}/download', [OrdenController::class, 'downloadDocument'])->middleware('permission:ordenes.ver');

    Route::get('/', [OrdenController::class, 'index'])->middleware('permission:ordenes.ver');
    Route::post('/', [OrdenController::class, 'store'])->middleware('permission:ordenes.crear');
    Route::get('/{orden}', [OrdenController::class, 'show'])->middleware('permission:ordenes.ver');
    Route::post('/{orden}/review', [OrdenController::class, 'review'])
        ->middleware('permission:ordenes.aprobar_director|ordenes.aprobar_gerencia');
    Route::post('/{orden}/resubmit', [OrdenController::class, 'resubmit'])->middleware('permission:ordenes.crear');
    Route::post('/{orden}/documents', [OrdenController::class, 'storeDocument'])->middleware('permission:ordenes.ver');
});
