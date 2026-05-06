<?php

use App\Http\Modules\Evaluaciones\Controller\EvaluationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/evaluations/history', [EvaluationController::class, 'globalHistory']);
    Route::get('/users/{user}/evaluations', [EvaluationController::class, 'show']);
    Route::post('/users/{user}/evaluations', [EvaluationController::class, 'store']);
    Route::get('/users/{user}/history', [EvaluationController::class, 'history']);
    Route::get('/evaluations/{evaluation}/export', [EvaluationController::class, 'exportPdf']);
    Route::get('/dashboard/export', [EvaluationController::class, 'exportDashboard']);
});
