<?php

use App\Http\Modules\Ftra\Controller\FormatController;
use App\Http\Modules\Ftra\Controller\ContractorController;
use App\Http\Modules\Ftra\Controller\FtraRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('ftra')->middleware(['auth:sanctum', 'project'])->group(function () {
    // CRUD de Formatos
    Route::get('/formats', [FormatController::class, 'index'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/formats', [FormatController::class, 'store'])->middleware('permission:ftra.parametrizar');
    Route::get('/formats/{format}', [FormatController::class, 'show'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/formats/{format}', [FormatController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::put('/formats/{format}', [FormatController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::delete('/formats/{format}', [FormatController::class, 'destroy'])->middleware('permission:ftra.parametrizar');
    
    // Proveedores: solo consulta. Se crean al registrar el contrato en Contratación.
    Route::get('/contractors', [ContractorController::class, 'index'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::get('/contractors/{contractor}', [ContractorController::class, 'show'])->middleware('permission:ftra.ver|ftra.parametrizar');

    // Torres del proyecto activo con su distribución de pisos y apartamentos (se configuran en Administración › Proyectos).
    // El residente responsable es el usuario que registra el FTRA: ya no hay catálogo de residentes.
    Route::get('/towers', [FtraRecordController::class, 'towers'])->middleware('permission:ftra.ver');
    Route::get('/towers/{tower}/units', [FtraRecordController::class, 'unitsStatus'])->middleware('permission:ftra.ver');

    // CRUD de Registros FTRA (Auditorías operativas)
    Route::get('/records', [FtraRecordController::class, 'index'])->middleware('permission:ftra.ver');
    Route::get('/records/export', [FtraRecordController::class, 'export'])->middleware('permission:ftra.ver');
    Route::post('/records', [FtraRecordController::class, 'store'])->middleware('permission:ftra.crear');
    Route::get('/records/{record}', [FtraRecordController::class, 'show'])->middleware('permission:ftra.ver');
    Route::post('/records/{record}', [FtraRecordController::class, 'update'])->middleware('permission:ftra.editar|ftra.revisar');
    Route::put('/records/{record}', [FtraRecordController::class, 'update'])->middleware('permission:ftra.editar|ftra.revisar');
    // Flujo: Residente registra → Supervisor (ftra.revisar) → Director de Obra (ftra.aprobar); devolución y reenvío
    Route::post('/records/{record}/approve', [FtraRecordController::class, 'approve'])->middleware('permission:ftra.revisar|ftra.aprobar');
    Route::post('/records/{record}/return', [FtraRecordController::class, 'returnRecord'])->middleware('permission:ftra.revisar|ftra.aprobar');
    Route::post('/records/{record}/resubmit', [FtraRecordController::class, 'resubmit'])->middleware('permission:ftra.crear|ftra.editar');
    Route::delete('/records/{record}', [FtraRecordController::class, 'destroy'])->middleware('permission:ftra.eliminar');
});
