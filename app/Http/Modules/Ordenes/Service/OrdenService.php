<?php

namespace App\Http\Modules\Ordenes\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Models\OrdenHistory;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrdenService
{
    public const SCOPE_MINE = 'mine';
    public const SCOPE_PENDING = 'pending'; // Órdenes que esperan una aprobación del usuario
    public const SCOPE_TASKS = 'tasks';     // Órdenes que esperan un documento que el usuario carga (Almacén / Facturación)
    public const SCOPE_ALL = 'all';

    public function __construct(
        protected OrdenStorageService $storage,
        protected OrdenNotificationService $notifications,
        protected PagoService $pagos,
    ) {
    }

    protected function listRelations(): array
    {
        return ['project:id,name', 'tower:id,name', 'creator:id,name'];
    }

    /**
     * Catálogos del formulario: proyectos (presupuestos) con sus torres y lo que el usuario puede hacer.
     */
    public function catalogs(User $user): array
    {
        $projectIds = $user->accessibleProjectIds();

        $projects = Project::query()
            ->where('is_active', true)
            ->when($projectIds !== null, fn ($q) => $q->whereIn('id', $projectIds))
            ->with(['towers' => fn ($q) => $q->orderBy('name')->select('id', 'project_id', 'name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Capítulos y actividades ya usados, como sugerencias para escribir igual que antes
        $used = Orden::query()->accessibleBy($user);

        return [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'towers' => $project->towers->map(fn ($tower) => ['id' => $tower->id, 'name' => $tower->name])->values(),
            ])->values(),
            'chapters' => (clone $used)->distinct()->orderBy('chapter')->limit(300)->pluck('chapter'),
            'activities' => (clone $used)->distinct()->orderBy('activity')->limit(300)->pluck('activity'),
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'max_upload_mb' => config('ordenes.max_upload_mb'),
            'review_stages' => OrdenWorkflow::stagesFor($user),
            'task_steps' => OrdenWorkflow::stepsFor($user),
            'can_view_all' => $user->can(OrdenWorkflow::PERMISSION_VIEW_ALL),
            'can_create' => $user->can('ordenes.crear'),
            ...OrdenWorkflow::toArray(),
        ];
    }

    public function listOrdenes(User $user, string $scope, array $filters = [])
    {
        $query = $this->scopedQuery($user, $scope)->with($this->listRelations());

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', $search)
                  ->orWhere('description', 'ilike', $search)
                  ->orWhere('chapter', 'ilike', $search)
                  ->orWhere('activity', 'ilike', $search)
                  ->orWhereHas('documents', fn ($sub) => $sub->where('number', 'ilike', $search));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (Orden $orden) => $this->present($orden, $user));
    }

    public function counts(User $user): array
    {
        return [
            self::SCOPE_MINE => $this->scopedQuery($user, self::SCOPE_MINE)->count(),
            self::SCOPE_PENDING => $this->scopedQuery($user, self::SCOPE_PENDING)->count(),
            self::SCOPE_TASKS => $this->scopedQuery($user, self::SCOPE_TASKS)->count(),
        ];
    }

    protected function scopedQuery(User $user, string $scope): Builder
    {
        $query = Orden::query();

        if ($scope === self::SCOPE_PENDING) {
            return $query->accessibleBy($user)->whereIn('current_stage', OrdenWorkflow::stagesFor($user) ?: ['__none__']);
        }

        if ($scope === self::SCOPE_TASKS) {
            return $query->accessibleBy($user)->whereIn('current_step', OrdenWorkflow::stepsFor($user) ?: ['__none__']);
        }

        if ($scope === self::SCOPE_ALL && $user->can(OrdenWorkflow::PERMISSION_VIEW_ALL)) {
            return $query->accessibleBy($user);
        }

        return $query->where('created_by', $user->id);
    }

    public function getOrden(Orden $orden, User $user): array
    {
        $orden->load([...$this->listRelations(), 'history.user:id,name', 'documents.uploader:id,name', 'documents.contractor:id,name,nit']);

        return $this->present($orden, $user, true);
    }

    /**
     * El residente que la registró, los aprobadores, los responsables de documentos y quienes tienen
     * ordenes.ver_todos (con acceso al proyecto) pueden consultar la orden.
     */
    public function canView(Orden $orden, User $user): bool
    {
        if ($orden->created_by === $user->id) {
            return true;
        }
        if (!$user->canAccessProject($orden->project_id)) {
            return false;
        }

        return $user->can(OrdenWorkflow::PERMISSION_VIEW_ALL)
            || OrdenWorkflow::stagesFor($user) !== []
            || OrdenWorkflow::stepsFor($user) !== [];
    }

    public function canReview(Orden $orden, User $user): bool
    {
        return $orden->current_stage !== null
            && in_array($orden->current_stage, OrdenWorkflow::stagesFor($user), true)
            && $user->canAccessProject($orden->project_id);
    }

    public function canResubmit(Orden $orden, User $user): bool
    {
        return $orden->status === OrdenWorkflow::STATUS_DEVUELTO && $orden->created_by === $user->id;
    }

    public function canUploadStep(Orden $orden, User $user): bool
    {
        return $orden->current_step !== null
            && in_array($orden->current_step, OrdenWorkflow::stepsFor($user), true)
            && $user->canAccessProject($orden->project_id);
    }

    /**
     * Registra la orden y la deja pendiente del Director de Obra.
     */
    public function createOrden(array $data, User $user): Orden
    {
        $firstStage = OrdenWorkflow::firstStage();

        $orden = DB::transaction(function () use ($data, $user, $firstStage) {
            $orden = Orden::create([
                ...$this->fields($data),
                'status' => OrdenWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
                'created_by' => $user->id,
            ]);
            $orden->update(['code' => 'ORD-' . str_pad((string) $orden->id, 4, '0', STR_PAD_LEFT)]);

            $this->log($orden, OrdenHistory::ACTION_REGISTRADA, $user, null, $data['comment'] ?? null);

            return $orden;
        });

        // Los correos se envían después del commit para no avisar de una orden que no quedó guardada
        $this->notifications->submitted($orden, false, $data['comment'] ?? null);

        return $orden;
    }

    /**
     * Aprueba o devuelve la orden en la etapa actual. Con la última aprobación pasa al primer documento (orden ERP).
     */
    public function review(Orden $orden, string $decision, ?string $comment, User $user): Orden
    {
        [$orden, $stage] = DB::transaction(function () use ($orden, $decision, $comment, $user) {
            // Bloqueo de fila: si dos aprobadores actúan a la vez, el segundo ve el estado ya actualizado
            $orden = Orden::whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if (!$this->canReview($orden, $user)) {
                throw new InvalidArgumentException('Esta orden ya no está pendiente de tu aprobación.');
            }

            $stage = $orden->current_stage;

            if ($decision === 'aprobar') {
                $nextStage = OrdenWorkflow::nextStage($stage);
                $firstStep = OrdenWorkflow::firstStep();
                $orden->update($nextStage
                    ? ['status' => OrdenWorkflow::STAGES[$nextStage]['status'], 'current_stage' => $nextStage]
                    : [
                        'status' => OrdenWorkflow::STEPS[$firstStep]['status'],
                        'current_stage' => null,
                        'current_step' => $firstStep,
                        'approved_at' => now(),
                    ]);
                $this->log($orden, OrdenHistory::ACTION_APROBADO, $user, $stage, $comment);
            } else {
                $orden->update([
                    'status' => OrdenWorkflow::STATUS_DEVUELTO,
                    'current_stage' => null,
                    'returned_stage' => $stage,
                ]);
                $this->log($orden, OrdenHistory::ACTION_DEVUELTO, $user, $stage, $comment);
            }

            return [$orden, $stage];
        });

        $decision === 'aprobar'
            ? $this->notifications->approved($orden, $stage, $comment)
            : $this->notifications->returned($orden, $stage, (string) $comment);

        return $orden;
    }

    /**
     * El residente corrige una orden devuelta y la reenvía: vuelve al Director de Obra.
     */
    public function resubmit(Orden $orden, array $data, User $user): Orden
    {
        if (!$this->canResubmit($orden, $user)) {
            throw new InvalidArgumentException('Solo el residente que registró la orden puede reenviarla cuando está devuelta.');
        }

        $firstStage = OrdenWorkflow::firstStage();

        $orden = DB::transaction(function () use ($orden, $data, $user, $firstStage) {
            $orden->update([
                ...$this->fields($data),
                'status' => OrdenWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
            ]);

            $this->log($orden, OrdenHistory::ACTION_REENVIADA, $user, null, $data['comment'] ?? null);

            return $orden;
        });

        $this->notifications->submitted($orden, true, $data['comment'] ?? null);

        return $orden;
    }

    /**
     * Carga el documento del paso actual (orden ERP, factura o entrada) y avanza al siguiente.
     */
    public function uploadDocument(Orden $orden, array $data, User $user): OrdenDocument
    {
        if (!$this->canUploadStep($orden, $user)) {
            throw new InvalidArgumentException('Esta orden no tiene un documento pendiente que puedas cargar.');
        }

        $step = $orden->current_step;
        $isInvoice = $step === OrdenWorkflow::STEP_FACTURA;
        if ($isInvoice && (empty($data['amount']) || empty($data['contractor_id']))) {
            throw new InvalidArgumentException('Indica el proveedor y el valor de la factura: con ellos pasa a cuentas por pagar.');
        }

        $file = $this->storage->claimStepFile($data['upload_token'], $user, $orden, $step);
        $observation = isset($data['observation']) ? trim($data['observation']) : null;

        [$orden, $document] = DB::transaction(function () use ($orden, $data, $user, $step, $file, $observation, $isInvoice) {
            $orden = Orden::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            if ($orden->current_step !== $step) {
                throw new InvalidArgumentException('Otro usuario ya cargó este documento.');
            }

            $document = $orden->documents()->create([
                'step' => $step,
                'number' => trim($data['number']),
                'amount' => $isInvoice ? $data['amount'] : null,
                'contractor_id' => $isInvoice ? $data['contractor_id'] : null,
                'observation' => $observation ?: null,
                'uploaded_by' => $user->id,
                ...$file,
            ]);

            $nextStep = OrdenWorkflow::nextStep($step);
            $orden->update($nextStep
                ? ['status' => OrdenWorkflow::STEPS[$nextStep]['status'], 'current_step' => $nextStep]
                : ['status' => OrdenWorkflow::STATUS_COMPLETADA, 'current_step' => null, 'completed_at' => now()]);

            $summary = OrdenWorkflow::STEPS[$step]['number_label'] . " {$document->number}" . ($observation ? ". {$observation}" : '');
            $orden->history()->create([
                'stage' => $step,
                'action' => OrdenHistory::ACTION_DOCUMENTO,
                'comment' => $summary,
                'file_name' => $document->file_name,
                'user_id' => $user->id,
            ]);

            // Orden completada: la factura del proveedor pasa a cuentas por pagar
            if ($orden->status === OrdenWorkflow::STATUS_COMPLETADA) {
                $this->pagos->registerFromOrden($orden, $user);
            }

            return [$orden, $document];
        });

        $this->notifications->documentUploaded($orden, $step, $observation);

        return $document;
    }

    public function documentDownloadUrl(OrdenDocument $document): string
    {
        return $this->storage->downloadUrl($document->file_path, $document->file_name);
    }

    protected function fields(array $data): array
    {
        return [
            'description' => trim($data['description']),
            'project_id' => $data['project_id'],
            'tower_id' => $data['tower_id'] ?? null,
            'chapter' => trim($data['chapter']),
            'activity' => trim($data['activity']),
        ];
    }

    protected function log(Orden $orden, string $action, User $user, ?string $stage, ?string $comment): void
    {
        $orden->history()->create([
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment ? trim($comment) : null,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Estado de cada aprobación (ciclo actual) y de cada documento, para la línea de tiempo.
     */
    public function progress(Orden $orden): array
    {
        $cycleStartId = $orden->history
            ->whereIn('action', [OrdenHistory::ACTION_REGISTRADA, OrdenHistory::ACTION_REENVIADA])
            ->max('id') ?? 0;
        $cycle = $orden->history->filter(fn (OrdenHistory $h) => $h->id > $cycleStartId);

        $stages = array_map(function (string $stage) use ($orden, $cycle) {
            $entry = $cycle->where('stage', $stage)->last();

            return [
                'key' => $stage,
                'label' => OrdenWorkflow::stageLabel($stage),
                'kind' => 'approval',
                'state' => match (true) {
                    $entry?->action === OrdenHistory::ACTION_APROBADO => 'aprobado',
                    $entry?->action === OrdenHistory::ACTION_DEVUELTO => 'devuelto',
                    $orden->current_stage === $stage => 'actual',
                    default => 'pendiente',
                },
                'user' => $entry?->user?->name,
                'at' => $entry?->created_at?->toIso8601String(),
            ];
        }, array_keys(OrdenWorkflow::STAGES));

        $steps = array_map(function (string $step) use ($orden) {
            $document = $orden->documents->firstWhere('step', $step);

            return [
                'key' => $step,
                'label' => OrdenWorkflow::STEPS[$step]['label'],
                'kind' => 'document',
                'state' => match (true) {
                    $document !== null => 'aprobado',
                    $orden->current_step === $step => 'actual',
                    default => 'pendiente',
                },
                'user' => $document?->uploader?->name,
                'at' => $document?->created_at?->toIso8601String(),
            ];
        }, array_keys(OrdenWorkflow::STEPS));

        return [...$stages, ...$steps];
    }

    protected function present(Orden $orden, User $user, bool $withDetail = false): array
    {
        $step = $orden->current_step;

        $data = [
            'id' => $orden->id,
            'code' => $orden->code,
            'description' => $orden->description,
            'project_id' => $orden->project_id,
            'project' => $orden->project,
            'tower_id' => $orden->tower_id,
            'tower' => $orden->tower,
            'chapter' => $orden->chapter,
            'activity' => $orden->activity,
            'status' => $orden->status,
            'status_label' => OrdenWorkflow::statusLabel($orden->status),
            'current_stage' => $orden->current_stage,
            'current_stage_label' => OrdenWorkflow::stageLabel($orden->current_stage),
            'current_step' => $step,
            'current_step_label' => OrdenWorkflow::stageLabel($step),
            'current_step_number_label' => $step ? OrdenWorkflow::STEPS[$step]['number_label'] : null,
            'returned_stage_label' => OrdenWorkflow::stageLabel($orden->returned_stage),
            'created_by' => $orden->creator ? ['id' => $orden->creator->id, 'name' => $orden->creator->name] : null,
            'created_at' => $orden->created_at?->toIso8601String(),
            'updated_at' => $orden->updated_at?->toIso8601String(),
            'approved_at' => $orden->approved_at?->toIso8601String(),
            'completed_at' => $orden->completed_at?->toIso8601String(),
            'can_review' => $this->canReview($orden, $user),
            'can_resubmit' => $this->canResubmit($orden, $user),
            'can_upload_step' => $this->canUploadStep($orden, $user),
        ];

        if ($withDetail) {
            $data['progress'] = $this->progress($orden);
            $data['history'] = $orden->history->sortByDesc('id')->map(fn (OrdenHistory $h) => [
                'id' => $h->id,
                'action' => $h->action,
                'stage' => $h->stage,
                'stage_label' => OrdenWorkflow::stageLabel($h->stage),
                'comment' => $h->comment,
                'file_name' => $h->file_name,
                'user' => $h->user?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values();
            $data['documents'] = $orden->documents->map(fn (OrdenDocument $d) => [
                'id' => $d->id,
                'step' => $d->step,
                'step_label' => $d->stepLabel(),
                'number_label' => OrdenWorkflow::STEPS[$d->step]['number_label'] ?? 'N.º',
                'number' => $d->number,
                'amount' => $d->amount !== null ? (float) $d->amount : null,
                'contractor' => $d->contractor ? ['id' => $d->contractor->id, 'name' => $d->contractor->name, 'nit' => $d->contractor->nit] : null,
                'observation' => $d->observation,
                'file_name' => $d->file_name,
                'file_size' => $d->file_size,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
