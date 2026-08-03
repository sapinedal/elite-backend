<?php

use App\Http\Modules\Configuracion\Controller\ConfiguracionController;
use App\Http\Modules\Configuracion\Controller\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('configuracion')->middleware('auth:sanctum')->group(function () {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::put('projects/{project}', [ProjectController::class, 'update']);
    Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('areas', [ConfiguracionController::class, 'getAreas']);
    Route::post('areas', [ConfiguracionController::class, 'storeArea']);
    Route::put('areas/{area}', [ConfiguracionController::class, 'updateArea']);
    Route::delete('areas/{area}', [ConfiguracionController::class, 'destroyArea']);
    
    Route::get('areas/{area}/positions', [ConfiguracionController::class, 'getPositions']);
    Route::post('positions', [ConfiguracionController::class, 'storePosition']);
    Route::put('positions/{position}', [ConfiguracionController::class, 'updatePosition']);
    Route::delete('positions/{position}', [ConfiguracionController::class, 'destroyPosition']);
});
