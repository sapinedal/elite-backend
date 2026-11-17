<?php

namespace App\Http\Modules\Documental\Dav;

use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotAuthenticated;

/**
 * Estado compartido por los nodos WebDAV de una petición: el servicio documental,
 * el usuario autenticado con su clave de escritorio y la IP de origen.
 */
class DavContext
{
    public ?User $user = null;

    public function __construct(
        public DocumentalService $service,
        public ?string $ip = null,
    ) {
    }

    public function user(): User
    {
        if (!$this->user) {
            throw new NotAuthenticated('Se requiere autenticación.');
        }

        return $this->user;
    }

    /**
     * Ejecuta una operación del servicio traduciendo sus errores a respuestas WebDAV (403).
     */
    public function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (AuthorizationException|InvalidArgumentException $e) {
            throw new Forbidden($e->getMessage());
        }
    }

    public const SOURCE = ['source' => 'escritorio'];
}
