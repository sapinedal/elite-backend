<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
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

    /**
     * Devuelve la lista paginada de registros con filtros.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['contractor_id', 'format_id', 'status', 'search', 'per_page']);
        $records = $this->recordService->listRecords($filters);
        
        return response()->json($records);
    }

    /**
     * Muestra los detalles de un registro específico.
     */
    public function show(FtraRecord $record)
    {
        $record->load(['contractor', 'format', 'photos', 'registeredBy:id,name', 'responsable']);
        return response()->json($record);
    }

    /**
     * Registra una nueva auditoría operativa FTRA (con fotos opcionales).
     */
    public function store(StoreFtraRecordRequest $request)
    {
        $user = auth()->user() ?: \App\Http\Modules\Users\Models\User::first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $photos = $request->file('photos');
        $record = $this->recordService->createRecord($request->validated(), $user->id, $photos);

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
    public function destroy(FtraRecord $record)
    {
        $this->recordService->deleteRecord($record);

        return response()->json([
            'message' => 'Registro FTRA eliminado exitosamente.'
        ]);
    }
}
