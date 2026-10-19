<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Request\CreateFolderRequest;
use App\Http\Modules\Documental\Request\DeleteItemRequest;
use App\Http\Modules\Documental\Request\RenameItemRequest;
use App\Http\Modules\Documental\Request\SyncFolderRulesRequest;
use App\Http\Modules\Documental\Request\UpdateSettingsRequest;
use App\Http\Modules\Documental\Request\UploadFileRequest;
use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Documental\Service\DocumentalSettingsService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class DocumentalController extends Controller
{
    public function __construct(
        protected DocumentalService $documentalService,
        protected DocumentalSettingsService $settingsService
    ) {
    }

    /**
     * Ejecuta una operación del servicio y traduce sus excepciones a respuestas HTTP:
     * 403 sin permisos, 422 datos inválidos, 500 errores de S3 / Spaces.
     */
    protected function respond(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $operation(),
            ], $status);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error en módulo documental', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible completar la operación en el repositorio documental. Intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Navega por los contenidos del bucket.
     * GET /api/v1/documental?path=...&search=...&refresh=1
     */
    public function index(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->browse(
            (string) $request->query('path', ''),
            $request->user(),
            $request->query('search'),
            $request->boolean('refresh'),
            $request->ip(),
            $request->boolean('recursive')
        ));
    }

    /**
     * POST /api/v1/documental/folders
     */
    public function createFolder(CreateFolderRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->createFolder(
            (string) $request->input('parent_path', ''),
            $request->input('folder_name'),
            $request->user(),
            $request->ip()
        ), 201);
    }

    /**
     * POST /api/v1/documental/upload
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->uploadFiles(
            (string) $request->input('path', ''),
            $request->file('files', []),
            $request->user(),
            $request->ip()
        ), 201);
    }

    /**
     * DELETE /api/v1/documental/item
     */
    public function deleteItem(DeleteItemRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->deleteItem(
            $request->input('path'),
            $request->input('type'),
            $request->user(),
            $request->ip()
        ));
    }

    /**
     * POST /api/v1/documental/rename
     */
    public function renameItem(RenameItemRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->renameItem(
            $request->input('old_path'),
            $request->input('new_name'),
            $request->input('type'),
            $request->user(),
            $request->ip()
        ));
    }

    /**
     * GET /api/v1/documental/download?path=...
     */
    public function download(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        return $this->respond(fn () => $this->documentalService->getDownloadUrl(
            $request->query('path'),
            $request->user(),
            $request->ip()
        ));
    }

    /**
     * Permisos efectivos del usuario en la ruta y, si administra el módulo, las carpetas restringidas y los roles.
     * GET /api/v1/documental/permissions?path=...
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $effective = $this->documentalService->getUserPermissionsForPath($user, (string) $request->query('path', ''));

        $restrictedFolders = [];
        $roles = [];
        if ($effective['is_admin']) {
            $restrictedFolders = $this->documentalService->listRestrictedFolders();
            $roles = Role::select('id', 'name')->orderBy('name')->get();
        }

        return response()->json([
            'success' => true,
            'effective_permissions' => $effective,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
            'restricted_folders' => $restrictedFolders,
            'roles' => $roles,
        ]);
    }

    /**
     * Reemplaza los roles con acceso a una carpeta (lista vacía = quitar restricción).
     * PUT /api/v1/documental/permissions/folder
     */
    public function syncFolderRules(SyncFolderRulesRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->documentalService->syncFolderRules(
            $request->input('folder_path'),
            $request->input('rules', []),
            $request->user(),
            $request->ip()
        ));
    }

    /**
     * Historial de actividad del repositorio documental, con filtros.
     * GET /api/v1/documental/logs?action=&user=&path=&date_from=&date_to=&page=&per_page=
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'nullable|string|max:50',
            'user' => 'nullable|string|max:100',
            'path' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $logs = DocumentActivityLog::with('user:id,name,email')
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('user'), fn ($q, $term) => $q->whereHas(
                'user',
                fn ($u) => $u->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%")
            ))
            ->when($request->query('path'), fn ($q, $term) => $q->where('path', 'ilike', "%{$term}%"))
            ->when($request->query('date_from'), fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($request->query('date_to'), fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'logs' => $logs,
            'denied_last_7_days' => DocumentActivityLog::where('action', 'denied')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ]);
    }

    /**
     * Configuración vigente del módulo (tamaño máximo, extensiones) y límites del servidor.
     * GET /api/v1/documental/settings
     */
    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->settingsService->payload()]);
    }

    /**
     * PUT /api/v1/documental/settings
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->settingsService->update($request->validated(), $request->user(), $request->ip()));
    }
}
