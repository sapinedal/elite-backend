<?php

use App\Http\Modules\Users\Controller\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/{id}/restore', [UserController::class, 'restore']);
    Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::get('/{user}', [UserController::class, 'show'])->withTrashed();
    Route::put('/{user}', [UserController::class, 'update'])->withTrashed();
    Route::delete('/{user}', [UserController::class, 'destroy']);
    Route::post('/{user}/change-password', [UserController::class, 'changePassword'])->withTrashed();
});
