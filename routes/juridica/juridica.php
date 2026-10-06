<?php

use App\Http\Modules\Juridica\Controller\ContractController;
use App\Http\Modules\Juridica\Controller\ContractTypeController;
use App\Http\Modules\Juridica\Controller\DriveSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('juridica')->middleware('auth:sanctum')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index'])->middleware('permission:juridica.ver|contratos.ver');
    Route::post('/contracts', [ContractController::class, 'store'])->middleware('permission:juridica.crear|contratos.crear');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->middleware('permission:juridica.editar|contratos.editar');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->middleware('permission:juridica.eliminar|contratos.eliminar');
    Route::get('/contracts/kpis', [ContractController::class, 'kpis'])->middleware('permission:juridica.ver|contratos.ver');

    Route::get('/contract-types', [ContractTypeController::class, 'index'])->middleware('permission:juridica.ver|contratos.ver');
    Route::post('/contract-types', [ContractTypeController::class, 'store'])->middleware('permission:juridica.crear|contratos.crear');
    Route::put('/contract-types/{id}', [ContractTypeController::class, 'update'])->middleware('permission:juridica.editar|contratos.editar');
    Route::delete('/contract-types/{id}', [ContractTypeController::class, 'destroy'])->middleware('permission:juridica.eliminar|contratos.eliminar');

    Route::get('/drive/folders/{folderId}', [DriveSyncController::class, 'getFolderFiles'])->middleware('permission:juridica.ver|contratos.ver');
});
