<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'project' => \App\Http\Middleware\ResolveActiveProject::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas JSON con mensajes comprensibles para el usuario final en la API.
        // La validación (422) conserva el formato estándar de Laravel con sus mensajes traducidos.
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (PostTooLargeException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }
            $maxMb = app(DocumentalSettingsService::class)->serverMaxUploadMb();

            return response()->json([
                'message' => "El archivo es demasiado grande. El tamaño máximo permitido por el servidor es de {$maxMb} MB.",
            ], 413);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? response()->json(['message' => 'Tu sesión expiró. Inicia sesión nuevamente.'], 401)
                : null;
        });

        $exceptions->render(function (UnauthorizedException|AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }
            // Los mensajes propios en español (ej. desde las policies) se respetan; los genéricos en inglés no
            $message = $e->getMessage();
            $isGeneric = $message === '' || $e instanceof UnauthorizedException || str_starts_with($message, 'This action is unauthorized');

            return response()->json([
                'message' => $isGeneric ? 'No tienes permisos para realizar esta acción.' : $message,
            ], 403);
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? response()->json(['message' => 'El registro que buscas no existe o fue eliminado.'], 404)
                : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? response()->json(['message' => 'Hiciste demasiadas solicitudes seguidas. Espera un momento e intenta de nuevo.'], 429)
                : null;
        });

        // Errores no controlados: en producción no se exponen detalles técnicos (SQL, rutas, trazas)
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            $handledByFramework = $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof ModelNotFoundException
                || $e instanceof RecordsNotFoundException
                || $e instanceof TokenMismatchException
                || $e instanceof SuspiciousOperationException;

            if (!$isApi($request) || config('app.debug') || $handledByFramework) {
                return null;
            }

            return response()->json([
                'message' => 'Ocurrió un error inesperado en el servidor. Intenta de nuevo; si el problema continúa, contacta a soporte.',
            ], 500);
        });
    })->create();
