<?php

use App\Http\Modules\Plantillas\Controller\KPIController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{user}/kpis', [KPIController::class, 'index']);
    Route::post('/users/{user}/kpis/sync', [KPIController::class, 'sync']);
    Route::delete('/kpis/{kpi}', [KPIController::class, 'destroy']);
});

