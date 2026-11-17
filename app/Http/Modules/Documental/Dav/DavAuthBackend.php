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
        if ($this->cookieUser && !$request->getHeader('Authorization')) {
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
