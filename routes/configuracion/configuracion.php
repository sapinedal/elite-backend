<?php

use App\Http\Modules\Configuracion\Controller\ConfiguracionController;
use App\Http\Modules\Configuracion\Controller\ProjectController;
use App\Http\Modules\Configuracion\Controller\TowerController;
use Illuminate\Support\Facades\Route;

Route::prefix('configuracion')->middleware('auth:sanctum')->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->middleware('permission:proyectos.ver|configuracion.ver');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('permission:proyectos.crear|configuracion.editar');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('permission:proyectos.ver|configuracion.ver');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('permission:proyectos.editar|configuracion.editar');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:proyectos.eliminar|configuracion.editar');

    Route::get('projects/{project}/towers', [TowerController::class, 'index'])->middleware('permission:proyectos.ver|configuracion.ver');
    Route::post('projects/{project}/towers', [TowerController::class, 'store'])->middleware('permission:proyectos.crear|configuracion.editar');
    Route::delete('towers/{tower}', [TowerController::class, 'destroy'])->middleware('permission:proyectos.eliminar|configuracion.editar');

    Route::get('areas', [ConfiguracionController::class, 'getAreas']);
    Route::post('areas', [ConfiguracionController::class, 'storeArea'])->middleware('permission:configuracion.editar');
    Route::put('areas/{area}', [ConfiguracionController::class, 'updateArea'])->middleware('permission:configuracion.editar');
    Route::delete('areas/{area}', [ConfiguracionController::class, 'destroyArea'])->middleware('permission:configuracion.editar');
    
    Route::get('areas/{area}/positions', [ConfiguracionController::class, 'getPositions']);
    Route::post('positions', [ConfiguracionController::class, 'storePosition'])->middleware('permission:configuracion.editar');
    Route::put('positions/{position}', [ConfiguracionController::class, 'updatePosition'])->middleware('permission:configuracion.editar');
    Route::delete('positions/{position}', [ConfiguracionController::class, 'destroyPosition'])->middleware('permission:configuracion.editar');
});
