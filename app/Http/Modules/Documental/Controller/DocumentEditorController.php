<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Documental\Service\DocumentEditorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DocumentEditorController extends Controller
{
    public function __construct(
        protected DocumentEditorService $editor,
        protected DocumentalService $documental,
    ) {
    }

    /**
     * Disponibilidad del editor y formatos soportados (para mostrar u ocultar las opciones en el frontend).
     * GET /api/v1/documental/editor/info
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->editor->isEnabled(),
                'editable' => array_keys(DocumentEditorService::EDITABLE),
                'viewable' => array_keys(DocumentEditorService::VIEWABLE),
                'new_file_types' => DocumentEditorService::NEW_FILE_TYPES,
            ],
        ]);
    }

    /**
     * Configuración firmada para abrir un archivo en ONLYOFFICE.
     * GET /api/v1/documental/editor/config?path=...
     */
    public function config(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string|max:1024']);
        $projectId = $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
        $this->documental->setActiveProject($projectId);

        return $this->respond(fn () => $this->editor->editorConfig(
            $request->query('path'),
            $request->user(),
            $projectId,
            $request->ip()
        ));
    }

    /**
     * Crea una hoja de cálculo, documento o presentación en blanco.
     * POST /api/v1/documental/editor/new
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'parent_path' => 'present|nullable|string|max:1024',
            'name' => 'required|string|max:150',
            'type' => 'required|string|in:' . implode(',', array_keys(DocumentEditorService::NEW_FILE_TYPES)),
        ]);
        $this->documental->setActiveProject($request->attributes->get(ResolveActiveProject::ATTRIBUTE));

        return $this->respond(fn () => $this->editor->createBlank(
            (string) $request->input('parent_path', ''),
            $request->input('name'),
            $request->input('type'),
            $request->user(),
            $request->ip()
        ), 201);
    }

    /**
     * Callback de ONLYOFFICE (sin sesión: URL firmada + JWT). Siempre responde {"error":0|1}.
     * POST /api/v1/documental/editor/callback?path=...&project=...&signature=...
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $this->editor->handleCallback(
                (string) $request->query('path'),
                $request->query('project') !== null ? (int) $request->query('project') : null,
                $request->json()->all(),
                // ONLYOFFICE usa el encabezado AuthorizationJwt (ver deploy/onlyoffice/docker-compose.yml)
                $request->header('AuthorizationJwt') ?? $request->header('Authorization')
            );

            return response()->json(['error' => 0]);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            Log::warning('Callback de ONLYOFFICE rechazado', ['path' => $request->query('path'), 'reason' => $e->getMessage()]);

            return response()->json(['error' => 1, 'message' => $e->getMessage()], $e instanceof AuthorizationException ? 403 : 422);
        } catch (\Throwable $e) {
            Log::error('Error guardando documento desde ONLYOFFICE', ['path' => $request->query('path'), 'exception' => $e]);

            return response()->json(['error' => 1], 500);
        }
    }

    protected function respond(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $operation()], $status);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error en el editor de documentos', ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'No fue posible abrir el editor. Intenta nuevamente.'], 500);
        }
    }
}
