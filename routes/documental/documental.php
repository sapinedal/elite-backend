<?php

use App\Http\Modules\Documental\Controller\DocumentalController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas modulares para el Módulo de Gestión Documental (S3 / DigitalOcean Spaces).
 */
Route::prefix('documental')->middleware('auth:sanctum')->group(function () {
    
    // Navegación del bucket S3 y listado de contenidos
    Route::get('/', [DocumentalController::class, 'index']);
    
    // Crear carpetas en el bucket
    Route::post('/folders', [DocumentalController::class, 'createFolder']);
    
    // Subir archivos a una carpeta del bucket
    Route::post('/upload', [DocumentalController::class, 'upload']);
    
    // Eliminar archivo o carpeta
    Route::delete('/item', [DocumentalController::class, 'deleteItem']);
    
    // Renombrar archivo o carpeta
    Route::post('/rename', [DocumentalController::class, 'renameItem']);
    
    // Descarga / URL firmada temporal de archivo
    Route::get('/download', [DocumentalController::class, 'download']);
    
    // Consulta y gestión de permisos por área
    Route::get('/permissions', [DocumentalController::class, 'permissions']);
    Route::post('/permissions', [DocumentalController::class, 'storePermission']);
    Route::delete('/permissions/{id}', [DocumentalController::class, 'destroyPermission']);
    
    // Auditoría de actividad documental
    Route::get('/logs', [DocumentalController::class, 'logs']);
});
