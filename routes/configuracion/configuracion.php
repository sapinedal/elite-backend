<?php

use App\Http\Modules\Configuracion\Controller\ConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::prefix('configuracion')->middleware('auth:sanctum')->group(function () {
    Route::get('areas', [ConfiguracionController::class, 'getAreas']);
    Route::post('areas', [ConfiguracionController::class, 'storeArea']);
    Route::put('areas/{area}', [ConfiguracionController::class, 'updateArea']);
    Route::delete('areas/{area}', [ConfiguracionController::class, 'destroyArea']);
    
    Route::get('areas/{area}/positions', [ConfiguracionController::class, 'getPositions']);
    Route::post('positions', [ConfiguracionController::class, 'storePosition']);
    Route::put('positions/{position}', [ConfiguracionController::class, 'updatePosition']);
    Route::delete('positions/{position}', [ConfiguracionController::class, 'destroyPosition']);
});
