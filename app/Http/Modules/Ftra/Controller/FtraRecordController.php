<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Service\FtraRecordService;
use App\Http\Modules\Ftra\Request\StoreFtraRecordRequest;
use App\Http\Modules\Ftra\Request\UpdateFtraRecordRequest;
use App\Http\Modules\Ftra\Request\ReviewFtraRecordRequest;
use App\Http\Modules\Ftra\Service\FtraWorkflow;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Ftra\Models\FtraFormat;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ftra\Service\FtraExportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $filters = $request->only(['contractor_id', 'format_id', 'tower_id', 'status', 'search', 'per_page']);
        $user = $request->user();
        $projectId = $this->activeProjectId($request);
        $records = $this->recordService->listRecords($user, $projectId, $filters);

        // Cada fila trae lo que el usuario puede hacer (Revisar / Corregir / Ver), como en Cortes
        $payload = $records->toArray();
        $payload['data'] = $records->getCollection()->map(fn (FtraRecord $record) => [
            ...$record->toArray(),
            'current_stage' => FtraWorkflow::currentStage($record),
            'can' => [
                'review' => FtraWorkflow::canReview($user, $record),
                'resubmit' => FtraWorkflow::canResubmit($user, $record),
            ],
        ])->values()->all();

        return response()->json($payload);
    }

    /**
     * Informe en Excel: hoja de registros (con los filtros de la pantalla) y matriz por apartamento y formato.
     */
    public function export(Request $request, FtraExportService $exporter)
    {
        $user = $request->user();
        $projectId = $this->activeProjectId($request);
        $filters = $request->only(['contractor_id', 'format_id', 'tower_id', 'status', 'search']);

        $relations = ['tower:id,name', 'format:id,code,name', 'contractor:id,name,nit', 'registeredBy:id,name', 'supervisorUser:id,name', 'directorUser:id,name', 'history.user:id,name'];
        $records = $this->recordService->filteredQuery($user, $projectId, $filters)->with($relations)->orderByDesc('created_at')->get();
        $projectRecords = $this->recordService->filteredQuery($user, $projectId)->get(['id', 'tower_id', 'apartamento', 'format_id', 'status']);

        $labels = array_values(array_filter([
            !empty($filters['status']) ? 'Estado: ' . FtraWorkflow::statusLabel($filters['status']) : null,
            !empty($filters['format_id']) ? 'Formato: ' . FtraFormat::find($filters['format_id'])?->name : null,
            !empty($filters['contractor_id']) ? 'Proveedor: ' . FtraContractor::find($filters['contractor_id'])?->name : null,
            !empty($filters['tower_id']) ? 'Torre: ' . Tower::find($filters['tower_id'])?->name : null,
            !empty($filters['search']) ? 'Búsqueda: ' . $filters['search'] : null,
        ]));

        $project = $projectId ? Project::find($projectId) : null;
        $spreadsheet = $exporter->build($project, $records, $projectRecords, $labels);
        $name = 'Seguimiento FTRA ' . ($project?->name ? $project->name . ' ' : '') . now()->toDateString() . '.xlsx';

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Torres del proyecto activo con su distribución de pisos y apartamentos (se configura en Administración › Proyectos).
     */
    public function towers(Request $request)
    {
        $projectId = $this->activeProjectId($request);

        return response()->json([
            'data' => $projectId
                ? Tower::where('project_id', $projectId)->orderBy('id')->get(['id', 'project_id', 'name', 'code', 'floors', 'apartments_per_floor'])
                : [],
        ]);
    }

    /**
     * Último FTRA de cada apartamento de la torre (semaforización).
     */
    public function unitsStatus(Request $request, Tower $tower)
    {
        abort_unless($request->user()->canAccessProject($tower->project_id), 403, 'No tienes acceso al proyecto de esta torre.');

        return response()->json(['data' => $this->recordService->unitsStatus($tower)]);
    }

    /**
     * Muestra los detalles de un registro específico.
     */
    public function show(Request $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        $record->load(FtraRecord::DETAIL_RELATIONS);
        return response()->json($this->present($request, $record));
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
            'message' => 'Registro FTRA guardado. Se notificó al Supervisor.',
            'record' => $this->present($request, $record)
        ], 201);
    }

    /**
     * Actualiza un registro FTRA (añadiendo nuevas fotos opcionales).
     */
    public function update(UpdateFtraRecordRequest $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        abort_unless(
            in_array($record->status, [FtraWorkflow::STATUS_REGISTRADA, FtraWorkflow::STATUS_DEVUELTA], true),
            422,
            'El FTRA ya está en revisión del Director o aprobado: no se puede editar.'
        );
        $photos = $request->file('photos');
        $data = collect($request->validated())->except(['photos', 'comment'])->all();
        $updatedRecord = $this->recordService->updateRecord($record, $data, $photos);

        return response()->json([
            'message' => 'Registro FTRA actualizado exitosamente.',
            'record' => $this->present($request, $updatedRecord)
        ]);
    }

    /**
     * Supervisor / Director de Obra: recibe a satisfacción (firma) y el FTRA avanza a la siguiente etapa.
     */
    public function approve(ReviewFtraRecordRequest $request, FtraRecord $record)
    {
        $this->authorizeReview($request, $record);
        $updated = $this->recordService->approve($record, $request->user(), $request->validated('signature'), $request->validated('comment'));

        return response()->json([
            'message' => $updated->status === FtraWorkflow::STATUS_APROBADA
                ? 'FTRA recibido a satisfacción. El flujo terminó.'
                : 'FTRA recibido a satisfacción. Se notificó al Director de Obra.',
            'record' => $this->present($request, $updated),
        ]);
    }

    /**
     * Supervisor / Director de Obra: devuelve el FTRA al residente con el motivo.
     */
    public function returnRecord(ReviewFtraRecordRequest $request, FtraRecord $record)
    {
        $this->authorizeReview($request, $record);
        $updated = $this->recordService->returnRecord($record, $request->user(), $request->validated('comment'));

        return response()->json([
            'message' => 'FTRA devuelto al residente.',
            'record' => $this->present($request, $updated),
        ]);
    }

    /**
     * Residente: corrige el FTRA devuelto y lo reenvía al Supervisor.
     */
    public function resubmit(UpdateFtraRecordRequest $request, FtraRecord $record)
    {
        $this->authorizeProject($request, $record);
        abort_unless($record->status === FtraWorkflow::STATUS_DEVUELTA, 422, 'Solo se puede reenviar un FTRA devuelto.');
        abort_unless(FtraWorkflow::canResubmit($request->user(), $record), 403, 'Solo el residente que lo registró puede reenviarlo.');

        $data = collect($request->validated())->except(['photos', 'comment'])->all();
        $updated = $this->recordService->resubmit($record, $request->user(), $data, $request->file('photos'), $request->validated('comment'));

        return response()->json([
            'message' => 'FTRA reenviado. Se notificó al Supervisor.',
            'record' => $this->present($request, $updated),
        ]);
    }

    protected function authorizeReview(Request $request, FtraRecord $record): void
    {
        $this->authorizeProject($request, $record);
        $stage = FtraWorkflow::currentStage($record);
        abort_unless($stage !== null, 422, 'Este FTRA no está pendiente de revisión (' . FtraWorkflow::statusLabel($record->status) . ').');
        abort_unless(FtraWorkflow::canReview($request->user(), $record), 403, 'No tienes permiso para revisar como ' . FtraWorkflow::stageLabel($stage) . '.');
    }

    /** Registro con las acciones que el usuario puede hacer, para que el frontend muestre solo los botones válidos. */
    protected function present(Request $request, FtraRecord $record): array
    {
        $user = $request->user();

        return [
            ...$record->toArray(),
            'current_stage' => FtraWorkflow::currentStage($record),
            'progress' => FtraWorkflow::progress($record),
            'can' => [
                'review' => FtraWorkflow::canReview($user, $record),
                'resubmit' => FtraWorkflow::canResubmit($user, $record),
                'edit' => in_array($record->status, [FtraWorkflow::STATUS_REGISTRADA, FtraWorkflow::STATUS_DEVUELTA], true)
                    && ($record->registered_by_id === $user->id || $user->can('ftra.editar')),
            ],
        ];
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
