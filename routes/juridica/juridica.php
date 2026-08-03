<?php

use App\Http\Modules\Juridica\Controller\ContractController;
use App\Http\Modules\Juridica\Controller\DriveSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('juridica')->middleware('auth:sanctum')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/kpis', [ContractController::class, 'kpis']);
    Route::get('/drive/folders/{folderId}', [DriveSyncController::class, 'getFolderFiles']);
});
