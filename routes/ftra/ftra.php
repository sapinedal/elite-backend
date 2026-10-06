<?php

use App\Http\Modules\Ftra\Controller\FormatController;
use App\Http\Modules\Ftra\Controller\ContractorController;
use App\Http\Modules\Ftra\Controller\FtraRecordController;
use App\Http\Modules\Ftra\Controller\ResidenteController;
use Illuminate\Support\Facades\Route;

Route::prefix('ftra')->middleware('auth:sanctum')->group(function () {
    // CRUD de Formatos
    Route::get('/formats', [FormatController::class, 'index'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/formats', [FormatController::class, 'store'])->middleware('permission:ftra.parametrizar');
    Route::get('/formats/{format}', [FormatController::class, 'show'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/formats/{format}', [FormatController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::put('/formats/{format}', [FormatController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::delete('/formats/{format}', [FormatController::class, 'destroy'])->middleware('permission:ftra.parametrizar');
    
    // CRUD de Contratistas / Proveedores
    Route::get('/contractors', [ContractorController::class, 'index'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/contractors', [ContractorController::class, 'store'])->middleware('permission:ftra.parametrizar');
    Route::get('/contractors/{contractor}', [ContractorController::class, 'show'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::put('/contractors/{contractor}', [ContractorController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::delete('/contractors/{contractor}', [ContractorController::class, 'destroy'])->middleware('permission:ftra.parametrizar');

    // CRUD de Residentes / Responsables
    Route::get('/residentes', [ResidenteController::class, 'index'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::post('/residentes', [ResidenteController::class, 'store'])->middleware('permission:ftra.parametrizar');
    Route::get('/residentes/{residente}', [ResidenteController::class, 'show'])->middleware('permission:ftra.ver|ftra.parametrizar');
    Route::put('/residentes/{residente}', [ResidenteController::class, 'update'])->middleware('permission:ftra.parametrizar');
    Route::delete('/residentes/{residente}', [ResidenteController::class, 'destroy'])->middleware('permission:ftra.parametrizar');

    // CRUD de Registros FTRA (Auditorías operativas)
    Route::get('/records', [FtraRecordController::class, 'index'])->middleware('permission:ftra.ver');
    Route::post('/records', [FtraRecordController::class, 'store'])->middleware('permission:ftra.crear');
    Route::get('/records/{record}', [FtraRecordController::class, 'show'])->middleware('permission:ftra.ver');
    Route::post('/records/{record}', [FtraRecordController::class, 'update'])->middleware('permission:ftra.editar|ftra.revisar');
    Route::put('/records/{record}', [FtraRecordController::class, 'update'])->middleware('permission:ftra.editar|ftra.revisar');
    Route::put('/records/{record}/status', [FtraRecordController::class, 'updateStatus'])->middleware('permission:ftra.revisar|ftra.aprobar');
    Route::delete('/records/{record}', [FtraRecordController::class, 'destroy'])->middleware('permission:ftra.eliminar');
});
