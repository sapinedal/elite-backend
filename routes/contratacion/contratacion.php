<?php

use App\Http\Modules\Contratacion\Controller\ContractController;
use App\Http\Modules\Contratacion\Controller\ContractDocumentController;
use App\Http\Modules\Juridica\Controller\ContractTypeController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Contratación: contratos de obra por proyecto activo (header X-Project-Id),
 * adjuntos en S3 (tecnica/<carpeta del proyecto>/...) y alertas de pólizas.
 */
Route::prefix('contratacion')->middleware(['auth:sanctum', 'project'])->group(function () {
    Route::get('/catalogs', [ContractController::class, 'catalogs'])->middleware('permission:contratacion.ver');
    Route::post('/towers', [ContractController::class, 'storeTower'])->middleware('permission:contratacion.crear');
    // Tipos de contrato (catálogo compartido con Jurídica, administrado desde Contratación)
    Route::get('/contract-types', [ContractTypeController::class, 'index'])->middleware('permission:contratacion.ver');
    Route::post('/contract-types', [ContractTypeController::class, 'store'])->middleware('permission:contratacion.crear|contratacion.editar');
    Route::put('/contract-types/{id}', [ContractTypeController::class, 'update'])->middleware('permission:contratacion.editar');
    Route::delete('/contract-types/{id}', [ContractTypeController::class, 'destroy'])->middleware('permission:contratacion.editar|contratacion.eliminar');

    Route::get('/alerts', [ContractController::class, 'alerts'])->middleware('permission:contratacion.ver');

    Route::get('/contracts', [ContractController::class, 'index'])->middleware('permission:contratacion.ver');
    Route::post('/contracts', [ContractController::class, 'store'])->middleware('permission:contratacion.crear');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->middleware('permission:contratacion.ver');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->middleware('permission:contratacion.editar');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->middleware('permission:contratacion.eliminar');

    // Adjuntos del contrato (01 DOCUMENTACIÓN LEGAL ... 07 OTROS)
    Route::post('/contracts/{contract}/documents', [ContractDocumentController::class, 'store'])->middleware('permission:contratacion.crear|contratacion.editar');
    Route::get('/documents/{document}/download', [ContractDocumentController::class, 'download'])->middleware('permission:contratacion.ver');
    Route::delete('/documents/{document}', [ContractDocumentController::class, 'destroy'])->middleware('permission:contratacion.editar');
});
