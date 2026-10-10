<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Models\DocumentAreaPermission;
use App\Http\Modules\Documental\Request\CreateFolderRequest;
use App\Http\Modules\Documental\Request\DeleteItemRequest;
use App\Http\Modules\Documental\Request\RenameItemRequest;
use App\Http\Modules\Documental\Request\StoreAreaPermissionRequest;
use App\Http\Modules\Documental\Request\UploadFileRequest;
use App\Http\Modules\Documental\Service\DocumentalService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentalController extends Controller
{
    protected DocumentalService $documentalService;

    public function __construct(DocumentalService $documentalService)
    {
        $this->documentalService = $documentalService;
    }

    /**
     * Obtiene el usuario autenticado (o fallback seguro).
     */
    protected function getAuthUser(): ?User
    {
        return auth()->user() ?: User::first();
    }

    /**
     * Navega por los contenidos del bucket S3 / DigitalOcean Spaces.
     * GET /api/v1/documental?path=...&search=...
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            $path = $request->query('path', '');
            $search = $request->query('search');
            $refresh = $request->boolean('refresh');

            $data = $this->documentalService->browse($path, $user, $search, $refresh);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Crea una nueva carpeta en la ruta indicada.
     * POST /api/v1/documental/folders
     */
    public function createFolder(CreateFolderRequest $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $parentPath = $request->input('parent_path', '');
            $folderName = $request->input('folder_name');

            $result = $this->documentalService->createFolder($parentPath, $folderName, $user, $request->ip());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Sube uno o más archivos a la ruta indicada en el bucket.
     * POST /api/v1/documental/upload
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $targetPath = $request->input('path', '');
            $files = $request->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }

            $result = $this->documentalService->uploadFiles($targetPath, $files, $user, $request->ip());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Elimina un archivo o carpeta en S3.
     * DELETE /api/v1/documental/item
     */
    public function deleteItem(DeleteItemRequest $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $path = $request->input('path');
            $type = $request->input('type');

            $result = $this->documentalService->deleteItem($path, $type, $user, $request->ip());

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Renombra o mueve un elemento en S3.
     * POST /api/v1/documental/rename
     */
    public function renameItem(RenameItemRequest $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $oldPath = $request->input('old_path');
            $newName = $request->input('new_name');
            $type = $request->input('type');

            $result = $this->documentalService->renameItem($oldPath, $newName, $type, $user, $request->ip());

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Genera una URL firmada de descarga para un archivo específico.
     * GET /api/v1/documental/download?path=...
     */
    public function download(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $filePath = $request->query('path');
            if (empty($filePath)) {
                return response()->json(['message' => 'Ruta de archivo requerida'], 422);
            }

            $result = $this->documentalService->getDownloadUrl($filePath, $user, $request->ip());

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Devuelve los permisos del usuario para la ruta actual y, si es admin, el listado de reglas por área.
     * GET /api/v1/documental/permissions?path=...
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        $path = $request->query('path', '');

        $effective = $this->documentalService->getUserPermissionsForPath($user, $path);

        $rules = [];
        $areas = [];

        // Si es administrador o tiene rol de gestión, entregamos las reglas de permisos por área
        if ($effective['is_admin']) {
            $rules = DocumentAreaPermission::with('area:id,name')->orderBy('area_id')->get();
            $areas = Area::select('id', 'name')->orderBy('name')->get();
        }

        return response()->json([
            'success' => true,
            'effective_permissions' => $effective,
            'user' => [
                'id' => optional($user)->id,
                'name' => optional($user)->name,
                'area' => optional(optional($user)->area)->name,
                'position' => optional(optional($user)->position)->name,
            ],
            'rules' => $rules,
            'areas' => $areas,
        ]);
    }

    /**
     * Crea o actualiza una regla de permisos para un área específica.
     * POST /api/v1/documental/permissions
     */
    public function storePermission(StoreAreaPermissionRequest $request): JsonResponse
    {
        $user = $this->getAuthUser();
        $effective = $this->documentalService->getUserPermissionsForPath($user, '');

        if (!$effective['is_admin']) {
            return response()->json(['message' => 'Solo los administradores pueden configurar permisos por área.'], 403);
        }

        $validated = $request->validated();
        $folderPath = $this->documentalService->normalizePath($validated['folder_path'] ?? '*');
        if (empty($folderPath)) {
            $folderPath = '*';
        }

        $permission = DocumentAreaPermission::updateOrCreate(
            [
                'area_id' => $validated['area_id'],
                'folder_path' => $folderPath,
            ],
            [
                'can_read' => $validated['can_read'] ?? true,
                'can_upload' => $validated['can_upload'] ?? false,
                'can_create_folder' => $validated['can_create_folder'] ?? false,
                'can_delete' => $validated['can_delete'] ?? false,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Permiso de área actualizado exitosamente.',
            'permission' => $permission->load('area:id,name'),
        ]);
    }

    /**
     * Elimina una regla de permisos por área.
     * DELETE /api/v1/documental/permissions/{id}
     */
    public function destroyPermission(int $id): JsonResponse
    {
        $user = $this->getAuthUser();
        $effective = $this->documentalService->getUserPermissionsForPath($user, '');

        if (!$effective['is_admin']) {
            return response()->json(['message' => 'Solo los administradores pueden eliminar permisos.'], 403);
        }

        $rule = DocumentAreaPermission::findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Regla de permisos eliminada.',
        ]);
    }

    /**
     * Lista el historial de actividad y auditoría sobre el repositorio documental.
     * GET /api/v1/documental/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
        $effective = $this->documentalService->getUserPermissionsForPath($user, '');

        if (!$effective['is_admin']) {
            return response()->json(['message' => 'No autorizado para ver auditoría.'], 403);
        }

        $logs = DocumentActivityLog::with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }
}
