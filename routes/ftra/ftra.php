<?php

use App\Http\Modules\Ftra\Controller\FormatController;
use App\Http\Modules\Ftra\Controller\ContractorController;
use App\Http\Modules\Ftra\Controller\FtraRecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('ftra')->middleware('auth:sanctum')->group(function () {
    
    // CRUD de Formatos
    Route::get('/formats', [FormatController::class, 'index']);
    Route::post('/formats', [FormatController::class, 'store']);
    Route::get('/formats/{format}', [FormatController::class, 'show']);
    Route::post('/formats/{format}', [FormatController::class, 'update']); // Soporta multipart/form-data con subida de PDF
    Route::put('/formats/{format}', [FormatController::class, 'update']);
    Route::delete('/formats/{format}', [FormatController::class, 'destroy']);
    
    // CRUD de Contratistas / Proveedores
    Route::get('/contractors', [ContractorController::class, 'index']);
    Route::post('/contractors', [ContractorController::class, 'store']);
    Route::get('/contractors/{contractor}', [ContractorController::class, 'show']);
    Route::put('/contractors/{contractor}', [ContractorController::class, 'update']);
    Route::delete('/contractors/{contractor}', [ContractorController::class, 'destroy']);

    // CRUD de Registros FTRA (Auditorías operativas)
    Route::get('/records', [FtraRecordController::class, 'index']);
    Route::post('/records', [FtraRecordController::class, 'store']);
    Route::get('/records/{record}', [FtraRecordController::class, 'show']);
    Route::post('/records/{record}', [FtraRecordController::class, 'update']); // Soporta multipart/form-data para adjuntar fotos
    Route::put('/records/{record}', [FtraRecordController::class, 'update']);
    Route::put('/records/{record}/status', [FtraRecordController::class, 'updateStatus']);
    Route::delete('/records/{record}', [FtraRecordController::class, 'destroy']);
});
