<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Service\FtraRecordService;
use App\Http\Modules\Ftra\Request\StoreFtraRecordRequest;
use App\Http\Modules\Ftra\Request\UpdateFtraRecordRequest;
use Illuminate\Http\Request;

class FtraRecordController extends Controller
{
    protected $recordService;

    public function __construct(FtraRecordService $recordService)
    {
        $this->recordService = $recordService;
    }

    protected function activeProjectId(Request $request): ?int
    {
        return $request->attributes->get(ResolveActiveProject::ATTRIBUTE);
    }

    /**
     * Corta la petición si el registro pertenece a un proyecto al que el usuario no tiene acceso.
     */
    protected function authorizeProject(Request $request, FtraRecord $record): void
    {
        abort_unless($request->user()->canAccessProject($record->project_id), 403, 'No tienes acceso al proyecto de este registro.');
    }

    /**
     * Devuelve la lista paginada de registros con filtros.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['contractor_id', 'format_id', 'status', 'search', 'per_page']);
        $records = $this->recordService->listRecords($request->user(), $this->activeProjectId($request), $filters);
        
        return response()->json($records);
    }

    /**
     * Muestra los detalles de un registro específico.
     */
    public function show(Request $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        $record->load(['contractor', 'format', 'photos', 'registeredBy:id,name', 'responsable']);
        return response()->json($record);
    }

    /**
     * Registra una nueva auditoría operativa FTRA (con fotos opcionales).
     */
    public function store(StoreFtraRecordRequest $request)
    {
        $projectId = $this->activeProjectId($request);
        if (!$projectId) {
            return response()->json(['message' => 'Selecciona un proyecto activo para registrar el FTRA.'], 422);
        }

        $photos = $request->file('photos');
        $record = $this->recordService->createRecord(
            [...$request->validated(), 'project_id' => $projectId],
            $request->user()->id,
            $photos
        );

        return response()->json([
            'message' => 'Registro FTRA guardado exitosamente.',
            'record' => $record
        ], 201);
    }

    /**
     * Actualiza un registro FTRA (añadiendo nuevas fotos opcionales).
     */
    public function update(UpdateFtraRecordRequest $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        $photos = $request->file('photos');
        $updatedRecord = $this->recordService->updateRecord($record, $request->validated(), $photos);

        return response()->json([
            'message' => 'Registro FTRA actualizado exitosamente.',
            'record' => $updatedRecord
        ]);
    }

    /**
     * Actualiza el estado de un registro FTRA.
     */
    public function updateStatus(Request $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        $request->validate([
            'status' => 'required|string|in:Registrada,Seguimiento,Aprobada,Rechazada'
        ]);

        $updatedRecord = $this->recordService->updateStatus($record, $request->status);

        return response()->json([
            'message' => 'Estado actualizado exitosamente.',
            'record' => $updatedRecord
        ]);
    }

    /**
     * Elimina un registro y sus fotos.
     */
    public function destroy(Request $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        $this->recordService->deleteRecord($record);

        return response()->json([
            'message' => 'Registro FTRA eliminado exitosamente.'
        ]);
    }
}
