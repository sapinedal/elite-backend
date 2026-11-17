<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Documental\Dav\DavAuthBackend;
use App\Http\Modules\Documental\Dav\DavCaptureSapi;
use App\Http\Modules\Documental\Dav\DavContext;
use App\Http\Modules\Documental\Dav\DavDirectory;
use App\Http\Modules\Documental\Dav\DavFastDownloadPlugin;
use App\Http\Modules\Documental\Dav\DavTemporaryFilesPlugin;
use App\Http\Modules\Documental\Service\DesktopKeyService;
use App\Http\Modules\Documental\Service\DocumentalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        protected DavLoginController $loginController,
    ) {
    }

    public function handle(Request $request)
    {
        $context = new DavContext($this->documentalService, $request->ip());
        $throttleKey = 'documental-dav-auth:' . $request->ip();

        // Office (Word, Excel…) bloquea el inicio de sesión Basic: si llega sin credenciales ni sesión válida,
        // se le indica la ventana de inicio de sesión de Elite (MS-OFBA) en lugar de pedir usuario y clave
        $cookieUser = $this->loginController->userFromCookie($request);
        if (!$cookieUser && !$request->headers->has('Authorization') && $this->isOfficeClient($request)) {
            return response('', 403, [
                'X-FORMS_BASED_AUTH_REQUIRED' => DavLoginController::loginUrl(),
                'X-FORMS_BASED_AUTH_RETURN_URL' => DavLoginController::returnUrl(),
                'X-FORMS_BASED_AUTH_DIALOG_SIZE' => '460x560',
            ]);
        }

        $server = new Server(new DavDirectory($context), new DavCaptureSapi());
        $server->setBaseUri(self::BASE_URI);
        Server::$exposeVersion = false;
        $server->debugExceptions = false;

        $auth = new DavAuthBackend(function (string $username, string $password) use ($context, $request, $throttleKey) {
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
        }, $context, $cookieUser);
        $server->addPlugin(new AuthPlugin($auth));

        // Bloqueos (WebDAV clase 2): Finder y Windows montan en solo lectura si el servidor no los soporta
        $locksDir = storage_path('app/dav');
        if (!is_dir($locksDir)) {
            mkdir($locksDir, 0775, true);
        }
        $server->addPlugin(new LocksPlugin(new FileLocks($locksDir . '/locks')));
        $server->addPlugin(new DavTemporaryFilesPlugin($locksDir . '/tmp'));
        $server->addPlugin(new DavFastDownloadPlugin());

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

    /**
     * Word, Excel, PowerPoint y sus sondeos de protocolo, que aceptan inicio de sesión por formulario (MS-OFBA).
     * El Explorador de Windows (Microsoft-WebDAV-MiniRedir) y el Finder siguen con Basic.
     */
    protected function isOfficeClient(Request $request): bool
    {
        if (in_array(strtolower((string) $request->header('X-FORMS_BASED_AUTH_ACCEPTED')), ['t', 'f'], true)) {
            return true;
        }

        $agent = (string) $request->userAgent();

        return !str_contains($agent, 'WebDAV-MiniRedir')
            && preg_match('/Microsoft Office|MSOffice|Microsoft Office Protocol Discovery|Microsoft Office Existence Discovery/i', $agent) === 1;
    }
}
