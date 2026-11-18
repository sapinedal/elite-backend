<?php

use App\Http\Modules\Obra\Controller\InterventoriaController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Técnico: control de obra e interventoría.
 */
Route::prefix('obra')->middleware(['auth:sanctum', 'permission:obra.ver'])->group(function () {
    // Último informe semanal de interventoría (o uno puntual con ?numero=) y la serie histórica de avance
    Route::get('/interventoria', [InterventoriaController::class, 'show']);
});
