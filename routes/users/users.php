<?php

use App\Http\Modules\Users\Controller\UserController;
use App\Http\Modules\Users\Controller\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store'])->middleware('permission:usuarios.crear');
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:usuarios.editar');
    Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:usuarios.editar');
    Route::get('/{user}', [UserController::class, 'show'])->withTrashed()->middleware('permission:usuarios.ver');
    Route::put('/{user}', [UserController::class, 'update'])->withTrashed()->middleware('permission:usuarios.editar');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:usuarios.eliminar');
    Route::post('/{user}/change-password', [UserController::class, 'changePassword'])->withTrashed();
});

Route::prefix('roles')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.ver|roles.editar|usuarios.ver|usuarios.crear|usuarios.editar');
    Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.editar');
    Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:roles.ver');
    Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:roles.editar');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.editar');
});

Route::prefix('permissions')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [RoleController::class, 'permissionsCatalog'])->middleware('permission:permisos.ver|roles.ver|roles.editar|usuarios.ver|usuarios.crear|usuarios.editar');
});
