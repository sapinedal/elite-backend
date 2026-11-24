<?php

namespace App\Http\Modules\Contratacion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionDocument;
use App\Http\Modules\Contratacion\Request\UploadContractDocumentRequest;
use App\Http\Modules\Contratacion\Service\ContractDocumentCatalog;
use App\Http\Modules\Contratacion\Service\ContractService;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ContractDocumentController extends Controller
{
    public function __construct(
        protected ContractStorageService $storage,
        protected ContractService $contracts,
    ) {
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'No tienes acceso al proyecto de este contrato.'], 403);
    }

    public function store(UploadContractDocumentRequest $request, ContratacionContract $contract): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }

        try {
            $documents = $this->storage->uploadDocuments(
                $contract,
                $request->validated('folder'),
                $request->validated('document_type'),
                $request->file('files', []),
                $request->user()
            );

            return response()->json([
                'status' => 'success',
                'message' => count($documents) . ' archivo(s) subido(s) exitosamente.',
                'data' => $documents,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Error al subir los archivos: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request, ContratacionDocument $document): JsonResponse
    {
        if (!$request->user()->canAccessProject($document->contract->project_id)) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => ['download_url' => $this->storage->downloadUrl($document), 'name' => $document->name],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    public function destroy(Request $request, ContratacionDocument $document): JsonResponse
    {
        if (!$request->user()->canAccessProject($document->contract->project_id)) {
            return $this->forbidden();
        }

        try {
            $this->storage->deleteDocument($document);

            return response()->json(['status' => 'success', 'message' => 'Archivo eliminado exitosamente.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar el archivo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Carpetas del gestor documental para elegir una existente (?path=; sin ruta, la carpeta de contratos
     * del proyecto). El proyecto es el del contrato (?contract_id) o el activo.
     */
    public function browse(Request $request): JsonResponse
    {
        $contractId = $request->integer('contract_id') ?: null;
        $projectId = $contractId
            ? ContratacionContract::whereKey($contractId)->value('project_id')
            : $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
        $project = $projectId ? Project::find($projectId) : null;

        if (!$project) {
            return response()->json(['status' => 'error', 'message' => 'Selecciona un proyecto para ver sus carpetas.'], 422);
        }
        if (!$request->user()->canAccessProject($project->id)) {
            return $this->forbidden();
        }

        try {
            return response()->json(['status' => 'success', 'data' => $this->storage->browseFolders($project, $request->query('path'))]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cambia la carpeta principal del contrato por una carpeta existente del gestor documental.
     */
    public function setFolder(Request $request, ContratacionContract $contract): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }
        $path = (string) $request->validate(['path' => 'required|string|max:1024'])['path'];

        return $this->respondWithContract($contract, fn () => $this->storage->setContractFolder($contract, $path),
            'La carpeta del contrato quedó asociada.');
    }

    /**
     * Vincula un tipo de documento (01 DOCUMENTACIÓN LEGAL ... 07 OTROS) a una carpeta existente; path null lo quita.
     */
    public function setFolderLink(Request $request, ContratacionContract $contract, string $folder): JsonResponse
    {
        if (!$request->user()->canAccessProject($contract->project_id)) {
            return $this->forbidden();
        }
        if (!array_key_exists($folder, ContractDocumentCatalog::FOLDERS)) {
            return response()->json(['status' => 'error', 'message' => 'El tipo de documento indicado no existe.'], 404);
        }
        $path = $request->validate(['path' => 'nullable|string|max:1024'])['path'] ?? null;

        return $this->respondWithContract($contract, fn () => $this->storage->setFolderLink($contract, $folder, $path, $request->user()),
            $path ? 'La carpeta quedó vinculada a ' . ContractDocumentCatalog::FOLDERS[$folder] . '.' : 'Se quitó la carpeta vinculada.');
    }

    protected function respondWithContract(ContratacionContract $contract, callable $action, string $message): JsonResponse
    {
        try {
            $files = $action();

            return response()->json([
                'status' => 'success',
                'message' => "{$message} {$files} archivo(s) en total.",
                'data' => $this->contracts->getContract($contract->fresh()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Error al asociar la carpeta: ' . $e->getMessage()], 500);
        }
    }
}
