<?php

namespace App\Http\Modules\Documental\Dav;

use App\Http\Modules\Users\Models\User;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Autenticación de la unidad de red:
 * - Basic (correo + clave de escritorio): Explorador de Windows, Finder, RaiDrive, Mountain Duck.
 * - Cookie de sesión de Office (MS-OFBA), ya validada por el controlador: Word, Excel, PowerPoint.
 */
class DavAuthBackend extends AbstractBasic
{
    /**
     * @param callable(string, string): bool $validateBasic
     */
    public function __construct(
        protected $validateBasic,
        protected DavContext $context,
        protected ?User $cookieUser = null,
    ) {
        $this->setRealm('Elite - Gestion Documental');
    }

    public function check(RequestInterface $request, ResponseInterface $response)
    {
        // La sesión de Office es prueba suficiente: Office la envía junto con otras credenciales propias que no
        // sirven (las que Windows guardó para la unidad, NTLM…); si se probaran primero respondería 401 y Office
        // mostraría la alerta de "inicio de sesión no seguro" y volvería a pedir la ventana de Elite
        if ($this->cookieUser) {
            $this->context->user = $this->cookieUser;

            return [true, $this->principalPrefix . $this->cookieUser->id];
        }

        return parent::check($request, $response);
    }

    protected function validateUserPass($username, $password)
    {
        return ($this->validateBasic)((string) $username, (string) $password);
    }
}
