<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    require __DIR__ . '/auth/auth.php';
    require __DIR__ . '/users/users.php';
    require __DIR__ . '/evaluaciones/evaluaciones.php';
    require __DIR__ . '/plantillas/plantillas.php';
    require __DIR__ . '/configuracion/configuracion.php';
    require __DIR__ . '/tasks/tasks.php';
    require __DIR__ . '/ftra/ftra.php';
    require __DIR__ . '/juridica/juridica.php';
    
});
