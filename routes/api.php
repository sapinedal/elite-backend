<?php

use App\Http\Modules\Documental\Controller\DavController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    require __DIR__ . '/auth/auth.php';
    require __DIR__ . '/users/users.php';
    require __DIR__ . '/evaluaciones/evaluaciones.php';
    require __DIR__ . '/plantillas/plantillas.php';
    require __DIR__ . '/configuracion/configuracion.php';
    require __DIR__ . '/tasks/tasks.php';
    require __DIR__ . '/ftra/ftra.php';
    require __DIR__ . '/documental/documental.php';
    require __DIR__ . '/juridica/juridica.php';
    require __DIR__ . '/contratacion/contratacion.php';
    require __DIR__ . '/cortes/cortes.php';
    require __DIR__ . '/ordenes/ordenes.php';
    require __DIR__ . '/facturacion/facturacion.php';
    require __DIR__ . '/anticipos/anticipos.php';
    require __DIR__ . '/cuentas_cobro/cuentas_cobro.php';
    require __DIR__ . '/pagos/pagos.php';
    
});

// Unidad de red de Gestión Documental (WebDAV) para el Explorador de Windows y el Finder de macOS.
// Se autentica con correo + clave de escritorio (Basic sobre HTTPS) dentro del propio controlador.
Route::match(
    ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'],
    'dav/{path?}',
    [DavController::class, 'handle']
)->where('path', '.*')->name('documental.dav');
