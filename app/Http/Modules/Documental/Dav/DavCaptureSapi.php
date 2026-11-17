<?php

namespace App\Http\Modules\Documental\Dav;

use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi;

/**
 * En lugar de enviar la respuesta directamente (header()/echo), la guarda para que Laravel la devuelva
 * con su ciclo normal (middlewares, pruebas automatizadas).
 */
class DavCaptureSapi extends Sapi
{
    public static ?ResponseInterface $response = null;

    public static function sendResponse(ResponseInterface $response)
    {
        static::$response = $response;
    }
}
