<?php

namespace App\Http\Middleware;

use App\Http\Modules\Configuracion\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el proyecto activo que el frontend envía en el header X-Project-Id
 * (o en el parámetro project_id) y verifica que el usuario tenga acceso a él.
 *
 * Si no se envía proyecto, la petición sigue sin uno activo y los servicios
 * limitan la información a todos los proyectos accesibles del usuario.
 */
class ResolveActiveProject
{
    public const ATTRIBUTE = 'active_project_id';

    public function handle(Request $request, Closure $next): Response
    {
        $rawId = $request->header('X-Project-Id') ?? $request->query('project_id');

        if ($rawId === null || $rawId === '') {
            return $next($request);
        }

        if (!ctype_digit((string) $rawId) || !Project::whereKey((int) $rawId)->exists()) {
            return response()->json(['message' => 'El proyecto seleccionado no existe.'], 404);
        }

        $user = $request->user();
        if (!$user || !$user->canAccessProject((int) $rawId)) {
            return response()->json(['message' => 'No tienes acceso a este proyecto.'], 403);
        }

        $request->attributes->set(self::ATTRIBUTE, (int) $rawId);

        return $next($request);
    }
}
