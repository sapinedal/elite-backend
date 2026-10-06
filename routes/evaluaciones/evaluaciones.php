<?php

use App\Http\Modules\Evaluaciones\Controller\EvaluationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/evaluations/history', [EvaluationController::class, 'globalHistory'])->middleware('permission:kpi.historial|kpi.ver');
    Route::get('/users/{user}/evaluations', [EvaluationController::class, 'show'])->middleware('permission:kpi.ver|kpi.evaluar');
    Route::post('/users/{user}/evaluations', [EvaluationController::class, 'store'])->middleware('permission:kpi.evaluar');
    Route::get('/users/{user}/history', [EvaluationController::class, 'history'])->middleware('permission:kpi.historial|kpi.ver');
    Route::get('/evaluations/{evaluation}/export', [EvaluationController::class, 'exportPdf'])->middleware('permission:kpi.ver|kpi.historial');
    Route::get('/dashboard/export', [EvaluationController::class, 'exportDashboard'])->middleware('permission:kpi.ver');
});
