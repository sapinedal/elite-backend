<?php

use App\Http\Modules\Plantillas\Controller\KPIController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{user}/kpis', [KPIController::class, 'index'])->middleware('permission:kpi.ver|kpi.parametrizar');
    Route::post('/users/{user}/kpis/sync', [KPIController::class, 'sync'])->middleware('permission:kpi.parametrizar');
    Route::delete('/kpis/{kpi}', [KPIController::class, 'destroy'])->middleware('permission:kpi.parametrizar');
});
