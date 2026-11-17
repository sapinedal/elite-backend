<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Documental\Dav\DavCaptureSapi;
use App\Http\Modules\Documental\Dav\DavContext;
use App\Http\Modules\Documental\Dav\DavDirectory;
use App\Http\Modules\Documental\Dav\DavTemporaryFilesPlugin;
use App\Http\Modules\Documental\Service\DesktopKeyService;
use App\Http\Modules\Documental\Service\DocumentalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Sabre\DAV\Auth\Backend\BasicCallBack;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Locks\Backend\File as FileLocks;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Server;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\Response as SabreResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unidad de red de Gestión Documental (WebDAV): permite abrir el repositorio desde el Explorador de Windows
 * o el Finder de macOS con el correo del usuario y una clave de escritorio.
 * Los permisos, proyectos y auditoría son los mismos de la web porque todo pasa por DocumentalService.
 */
class DavController extends Controller
{
    public const BASE_URI = '/api/dav/';

    /** Intentos fallidos de clave permitidos por IP y minuto */
    protected const MAX_FAILED_ATTEMPTS = 10;

    public function __construct(
        protected DocumentalService $documentalService,
        protected DesktopKeyService $keyService,
    ) {
    }

    public function handle(Request $request)
    {
        $context = new DavContext($this->documentalService, $request->ip());
        $throttleKey = 'documental-dav-auth:' . $request->ip();

        $server = new Server(new DavDirectory($context), new DavCaptureSapi());
        $server->setBaseUri(self::BASE_URI);
        Server::$exposeVersion = false;
        $server->debugExceptions = false;

        $auth = new BasicCallBack(function (string $username, string $password) use ($context, $request, $throttleKey) {
            if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
                return false;
            }
            $user = $this->keyService->authenticate($username, $password, $request->ip());
            if (!$user) {
                RateLimiter::hit($throttleKey, 60);

                return false;
            }
            $context->user = $user;

            return true;
        });
        $auth->setRealm('Elite - Gestion Documental');
        $server->addPlugin(new AuthPlugin($auth));

        // Bloqueos (WebDAV clase 2): Finder y Windows montan en solo lectura si el servidor no los soporta
        $locksDir = storage_path('app/dav');
        if (!is_dir($locksDir)) {
            mkdir($locksDir, 0775, true);
        }
        $server->addPlugin(new LocksPlugin(new FileLocks($locksDir . '/locks')));
        $server->addPlugin(new DavTemporaryFilesPlugin($locksDir . '/tmp'));

        $sabreRequest = new SabreRequest(
            $request->getMethod(),
            $request->getRequestUri(),
            $request->headers->all(),
            $request->getContent(true)
        );
        $sabreRequest->setAbsoluteUrl($request->getUri());
        $server->httpRequest = $sabreRequest;
        $server->httpResponse = new SabreResponse();

        DavCaptureSapi::$response = null;
        $server->start();

        return $this->toLaravelResponse(DavCaptureSapi::$response ?? $server->httpResponse);
    }

    protected function toLaravelResponse(SabreResponse $response): StreamedResponse
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = $values;
        }

        $body = $response->getBody();

        return new StreamedResponse(function () use ($body) {
            if (is_resource($body)) {
                $out = fopen('php://output', 'wb');
                stream_copy_to_stream($body, $out);
                fclose($body);
            } elseif (is_callable($body)) {
                $body();
            } elseif ($body !== null) {
                echo $body;
            }
        }, $response->getStatus(), $headers);
    }
}
