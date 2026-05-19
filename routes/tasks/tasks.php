<?php

use App\Http\Modules\Tasks\Controller\TaskController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas Modulares del módulo de Bitácora de Tareas.
 * 
 * Ventaja técnica: Mantiene el archivo de rutas principal (api.php) limpio,
 * colocalizando las rutas específicas del dominio al lado de sus controladores y modelos.
 */
Route::prefix('tasks')->middleware('auth:sanctum')->group(function () {
    
    // CRUD Principal de la Bitácora
    Route::get('/', [TaskController::class, 'index']);
    Route::post('/', [TaskController::class, 'store']);
    Route::get('/{task}', [TaskController::class, 'show']);
    Route::put('/{task}', [TaskController::class, 'update']);
    Route::delete('/{task}', [TaskController::class, 'destroy']);
    
    // Dailys y observaciones
    Route::post('/{task}/observations', [TaskController::class, 'storeObservation']);
});
