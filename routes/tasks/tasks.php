<?php

use App\Http\Modules\Tasks\Controller\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('tasks')->middleware('auth:sanctum')->group(function () {
    // Bitácora abierta para todo el personal autenticado
    Route::get('/', [TaskController::class, 'index']);
    Route::post('/', [TaskController::class, 'store']);
    Route::get('/{task}', [TaskController::class, 'show']);
    Route::put('/{task}', [TaskController::class, 'update']);
    Route::delete('/{task}', [TaskController::class, 'destroy']);
    
    // Dailys y observaciones
    Route::post('/{task}/observations', [TaskController::class, 'storeObservation']);
});
