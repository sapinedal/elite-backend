<?php

namespace App\Http\Modules\Contratacion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionDocument;
use App\Http\Modules\Contratacion\Request\UploadContractDocumentRequest;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ContractDocumentController extends Controller
{
    public function __construct(protected ContractStorageService $storage)
    {
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
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar el archivo: ' . $e->getMessage()], 500);
        }
    }
}
