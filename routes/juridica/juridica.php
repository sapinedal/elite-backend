<?php

use App\Http\Modules\Juridica\Controller\ContractController;
use App\Http\Modules\Juridica\Controller\ContractTypeController;
use App\Http\Modules\Juridica\Controller\DriveSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('juridica')->middleware('auth:sanctum')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::put('/contracts/{contract}', [ContractController::class, 'update']);
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy']);
    Route::get('/contracts/kpis', [ContractController::class, 'kpis']);

    Route::get('/contract-types', [ContractTypeController::class, 'index']);
    Route::post('/contract-types', [ContractTypeController::class, 'store']);
    Route::put('/contract-types/{id}', [ContractTypeController::class, 'update']);
    Route::delete('/contract-types/{id}', [ContractTypeController::class, 'destroy']);

    Route::get('/drive/folders/{folderId}', [DriveSyncController::class, 'getFolderFiles']);
});
