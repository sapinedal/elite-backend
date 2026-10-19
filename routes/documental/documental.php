<?php

use App\Http\Modules\Documental\Controller\DocumentalController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas modulares para el Módulo de Gestión Documental (S3 / DigitalOcean Spaces).
 * El acceso al módulo exige `documental.ver`; las acciones (subir, crear, renombrar, eliminar)
 * se validan en DocumentalService combinando los permisos del rol con las restricciones por carpeta.
 */
Route::prefix('documental')->middleware(['auth:sanctum', 'permission:documental.ver'])->group(function () {

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

    // Permisos efectivos del usuario (y restricciones por carpeta si administra el módulo)
    Route::get('/permissions', [DocumentalController::class, 'permissions']);

    // Configuración del módulo (tamaño máximo y extensiones); la consulta la usa el formulario de subida
    Route::get('/settings', [DocumentalController::class, 'settings']);

    // Administración de restricciones por carpeta, configuración y auditoría
    Route::middleware('permission:documental.administrar')->group(function () {
        Route::put('/permissions/folder', [DocumentalController::class, 'syncFolderRules']);
        Route::put('/settings', [DocumentalController::class, 'updateSettings']);
        Route::get('/logs', [DocumentalController::class, 'logs']);
    });
});
