<?php

use App\Http\Modules\Tasks\Controller\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('tasks')->middleware('auth:sanctum')->group(function () {
    // CRUD Principal de la Bitácora
    Route::get('/', [TaskController::class, 'index'])->middleware('permission:bitacora.ver');
    Route::post('/', [TaskController::class, 'store'])->middleware('permission:bitacora.crear');
    Route::get('/{task}', [TaskController::class, 'show'])->middleware('permission:bitacora.ver');
    Route::put('/{task}', [TaskController::class, 'update'])->middleware('permission:bitacora.editar|bitacora.crear');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->middleware('permission:bitacora.eliminar');
    
    // Dailys y observaciones
    Route::post('/{task}/observations', [TaskController::class, 'storeObservation'])->middleware('permission:bitacora.crear|bitacora.ver');
});
