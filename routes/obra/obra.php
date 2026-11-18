<?php

use App\Http\Modules\Obra\Controller\InterventoriaController;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Técnico: control de obra e interventoría.
 */
Route::prefix('obra')->middleware(['auth:sanctum', 'permission:obra.ver'])->group(function () {
    // Último informe semanal de interventoría (o uno puntual con ?numero=) y la serie histórica de avance
    Route::get('/interventoria', [InterventoriaController::class, 'show']);
    // Botón "Sincronizar": trae lo nuevo de Drive (si está configurado) y carga el último informe
    Route::post('/interventoria/sincronizar', [InterventoriaController::class, 'sync'])->middleware('throttle:6,1');
});

// Fotos del registro fotográfico: enlaces firmados y temporales que entrega la API (se usan directo en <img>)
Route::get('/obra/interventoria/fotos/{numero}/{archivo}', [InterventoriaController::class, 'photo'])
    ->whereNumber('numero')
    ->where('archivo', 'foto-\\d{2,3}\\.jpg')
    ->middleware(['signed', 'throttle:240,1'])
    ->name('obra.interventoria.foto');
