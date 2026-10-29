<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroAdjunto;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroHistory;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cuentas de cobro:
 *
 *   Radicada (con adjuntos si el tipo los exige) -> Director de Obra -> Gerencia -> Paula decide:
 *     - no requiere Multifox: pasa al cuadro de pagos
 *     - requiere Multifox: pasa al cuadro de pagos cuando se carga en Multifox
 */
class CuentaCobroService
{
    public const SCOPE_MINE = 'mine';
    public const SCOPE_PENDING = 'pending';   // Esperan una aprobación del usuario
    public const SCOPE_MULTIFOX = 'multifox'; // Aprobadas que Paula debe revisar / cargar en Multifox
    public const SCOPE_ALL = 'all';

    public function __construct(
        protected CuentaCobroStorageService $storage,
        protected CuentaCobroNotificationService $notifications,
        protected CuentaCobroTipoService $tipos,
        protected PagoService $pagos,
    ) {
    }

    protected function listRelations(): array
    {
        return [
            'tipo:id,name,requires_attachment',
            'project:id,name',
            'tower:id,name',
            'contract:id,contract_code',
            'contractor:id,name,nit',
            'creator:id,name',
        ];
    }

    public function catalogs(User $user): array
    {
        $projectIds = $user->accessibleProjectIds();

        $projects = Project::query()
            ->where('is_active', true)
            ->when($projectIds !== null, fn ($q) => $q->whereIn('id', $projectIds))
            ->with(['towers' => fn ($q) => $q->orderBy('name')->select('id', 'project_id', 'name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'tipos' => CuentaCobroTipo::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'description', 'requires_attachment', 'attachment_hint']),
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'towers' => $project->towers->map(fn ($tower) => ['id' => $tower->id, 'name' => $tower->name])->values(),
            ])->values(),
            'contracts' => ContratacionContract::query()->accessibleBy($user)->orderBy('contract_code')
                ->get(['id', 'project_id', 'contract_code', 'contractor_name']),
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'max_upload_mb' => config('cuentas_cobro.max_upload_mb'),
            'review_stages' => CuentaCobroWorkflow::stagesFor($user),
            'can_view_all' => $user->can(CuentaCobroWorkflow::PERMISSION_VIEW_ALL),
            'can_create' => $user->can('cuentas_cobro.crear'),
            'can_handle_multifox' => CuentaCobroWorkflow::canHandleMultifox($user),
            'can_parametrize' => $user->can(CuentaCobroWorkflow::PERMISSION_PARAMETRIZAR),
            ...CuentaCobroWorkflow::toArray(),
        ];
    }

    public function listCuentas(User $user, string $scope, array $filters = [])
    {
        $query = $this->scopedQuery($user, $scope)->with($this->listRelations())->withCount('adjuntos');

        foreach (['status', 'project_id', 'tipo_id'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', $search)
                  ->orWhere('document_number', 'ilike', $search)
                  ->orWhere('multifox_number', 'ilike', $search)
                  ->orWhere('description', 'ilike', $search)
                  ->orWhereHas('contractor', fn ($sub) => $sub->where('name', 'ilike', $search)->orWhere('nit', 'ilike', $search));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (CuentaCobro $cuenta) => $this->present($cuenta, $user));
    }

    public function counts(User $user): array
    {
        return [
            self::SCOPE_MINE => $this->scopedQuery($user, self::SCOPE_MINE)->count(),
            self::SCOPE_PENDING => $this->scopedQuery($user, self::SCOPE_PENDING)->count(),
            self::SCOPE_MULTIFOX => CuentaCobroWorkflow::canHandleMultifox($user) ? $this->scopedQuery($user, self::SCOPE_MULTIFOX)->count() : 0,
        ];
    }

    protected function scopedQuery(User $user, string $scope): Builder
    {
        $query = CuentaCobro::query();

        if ($scope === self::SCOPE_PENDING) {
            return $query->accessibleBy($user)->whereIn('current_stage', CuentaCobroWorkflow::stagesFor($user) ?: ['__none__']);
        }

        if ($scope === self::SCOPE_MULTIFOX && CuentaCobroWorkflow::canHandleMultifox($user)) {
            return $query->accessibleBy($user)->whereIn('status', CuentaCobroWorkflow::MULTIFOX_STATUSES);
        }

        if ($scope === self::SCOPE_ALL && ($user->can(CuentaCobroWorkflow::PERMISSION_VIEW_ALL) || CuentaCobroWorkflow::canHandleMultifox($user))) {
            return $query->accessibleBy($user);
        }

        return $query->where('created_by', $user->id);
    }

    public function getCuenta(CuentaCobro $cuenta, User $user): array
    {
        $cuenta->load([...$this->listRelations(), 'tipo', 'history.user:id,name', 'adjuntos.uploader:id,name', 'multifoxUser:id,name', 'multifoxDecider:id,name']);

        return $this->present($cuenta, $user, true);
    }

    public function canView(CuentaCobro $cuenta, User $user): bool
    {
        if ($cuenta->created_by === $user->id) {
            return true;
        }
        if (!$user->canAccessProject($cuenta->project_id)) {
            return false;
        }

        return $user->can(CuentaCobroWorkflow::PERMISSION_VIEW_ALL)
            || CuentaCobroWorkflow::stagesFor($user) !== []
            || CuentaCobroWorkflow::canHandleMultifox($user);
    }

    public function canReview(CuentaCobro $cuenta, User $user): bool
    {
        return $cuenta->current_stage !== null
            && in_array($cuenta->current_stage, CuentaCobroWorkflow::stagesFor($user), true)
            && $user->canAccessProject($cuenta->project_id);
    }

    public function canResubmit(CuentaCobro $cuenta, User $user): bool
    {
        return $cuenta->status === CuentaCobroWorkflow::STATUS_DEVUELTA && $cuenta->created_by === $user->id;
    }

    public function canHandleMultifox(CuentaCobro $cuenta, User $user): bool
    {
        return in_array($cuenta->status, CuentaCobroWorkflow::MULTIFOX_STATUSES, true)
            && CuentaCobroWorkflow::canHandleMultifox($user)
            && $user->canAccessProject($cuenta->project_id);
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->storage->requestUpload($fileName, $fileSize, $mime, $user);
    }

    /**
     * Radica la cuenta de cobro con sus archivos ya subidos a S3 y la deja pendiente del Director de Obra.
     * Si el tipo exige adjunto (ej. SST, Gestión ambiental), al menos uno es obligatorio.
     */
    public function createCuenta(array $data, User $user): CuentaCobro
    {
        $tipo = $this->tipos->assertUsable(CuentaCobroTipo::find($data['tipo_id']));
        $project = $this->accessibleProject($data['project_id'], $user);
        $this->assertAttachments($tipo, count($data['attachment_tokens'] ?? []));
        $firstStage = CuentaCobroWorkflow::firstStage();

        $cuenta = DB::transaction(function () use ($data, $user, $tipo, $project, $firstStage) {
            $cuenta = CuentaCobro::create([
                ...$this->fields($data),
                'status' => CuentaCobroWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
                'created_by' => $user->id,
                'file_name' => '',
                'file_path' => '',
            ]);
            $cuenta->update(['code' => 'CC-' . str_pad((string) $cuenta->id, 4, '0', STR_PAD_LEFT)]);

            // La carpeta lleva el código: los archivos se mueven cuando ya está asignado
            $folder = $this->storage->folderFor($project, $tipo, $cuenta->code);
            $cuenta->update(['storage_path' => $folder, ...$this->storage->claimFile($data['upload_token'], $user, $folder)]);
            $this->attach($cuenta, $data['attachment_tokens'] ?? [], $user);

            $this->log($cuenta, CuentaCobroHistory::ACTION_RADICADA, $user, null, $data['comment'] ?? null, $cuenta->file_name);

            return $cuenta;
        });

        $this->notifications->submitted($cuenta, false, $data['comment'] ?? null);

        return $cuenta;
    }

    public function review(CuentaCobro $cuenta, string $decision, ?string $comment, User $user): CuentaCobro
    {
        [$cuenta, $stage] = DB::transaction(function () use ($cuenta, $decision, $comment, $user) {
            $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();

            if (!$this->canReview($cuenta, $user)) {
                throw new InvalidArgumentException('Esta cuenta de cobro ya no está pendiente de tu aprobación.');
            }

            $stage = $cuenta->current_stage;

            if ($decision === 'aprobar') {
                $nextStage = CuentaCobroWorkflow::nextStage($stage);
                $cuenta->update($nextStage
                    ? ['status' => CuentaCobroWorkflow::STAGES[$nextStage]['status'], 'current_stage' => $nextStage]
                    : ['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_REVISION, 'current_stage' => null, 'approved_at' => now()]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_APROBADO, $user, $stage, $comment);
            } else {
                $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_DEVUELTA, 'current_stage' => null, 'returned_stage' => $stage]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_DEVUELTO, $user, $stage, $comment);
            }

            return [$cuenta, $stage];
        });

        $decision === 'aprobar'
            ? $this->notifications->approved($cuenta, $stage, $comment)
            : $this->notifications->returned($cuenta, $stage, (string) $comment);

        return $cuenta;
    }

    /**
     * Se corrige una cuenta devuelta (datos, cuenta de cobro y/o adjuntos nuevos) y se reenvía al Director de Obra.
     * Los archivos nuevos quedan en la misma carpeta; los anteriores se conservan.
     */
    public function resubmit(CuentaCobro $cuenta, array $data, User $user): CuentaCobro
    {
        if (!$this->canResubmit($cuenta, $user)) {
            throw new InvalidArgumentException('Solo quien radicó la cuenta de cobro puede reenviarla cuando está devuelta.');
        }

        $tipo = $this->tipos->assertUsable(CuentaCobroTipo::find($data['tipo_id']));
        $this->accessibleProject($data['project_id'], $user);
        $this->assertAttachments($tipo, $cuenta->adjuntos()->count() + count($data['attachment_tokens'] ?? []));

        $file = !empty($data['upload_token']) ? $this->storage->claimFile($data['upload_token'], $user, $cuenta->storage_path) : [];
        $firstStage = CuentaCobroWorkflow::firstStage();

        $cuenta = DB::transaction(function () use ($cuenta, $data, $file, $user, $firstStage) {
            $cuenta->update([
                ...$this->fields($data),
                'status' => CuentaCobroWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
                ...$file,
            ]);
            $this->attach($cuenta, $data['attachment_tokens'] ?? [], $user);

            $this->log($cuenta, CuentaCobroHistory::ACTION_REENVIADA, $user, null, $data['comment'] ?? null, $file['file_name'] ?? null);

            return $cuenta;
        });

        $this->notifications->submitted($cuenta, true, $data['comment'] ?? null);

        return $cuenta;
    }

    /**
     * Paula decide si la cuenta requiere Multifox. Si no, pasa al cuadro de pagos; si sí, queda pendiente del cargue.
     */
    public function decideMultifox(CuentaCobro $cuenta, bool $requiresMultifox, ?string $comment, User $user): CuentaCobro
    {
        $comment = $comment !== null ? (trim($comment) ?: null) : null;

        $cuenta = DB::transaction(function () use ($cuenta, $requiresMultifox, $comment, $user) {
            $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();
            if ($cuenta->status !== CuentaCobroWorkflow::STATUS_PENDIENTE_REVISION || !$this->canHandleMultifox($cuenta, $user)) {
                throw new InvalidArgumentException('Esta cuenta de cobro no está pendiente de la revisión de Multifox.');
            }

            $cuenta->update([
                'requires_multifox' => $requiresMultifox,
                'multifox_decided_by' => $user->id,
                'multifox_decided_at' => now(),
            ]);

            if ($requiresMultifox) {
                $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_MULTIFOX]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_REQUIERE_MULTIFOX, $user, CuentaCobroWorkflow::STEP_MULTIFOX,
                    'Requiere cargue en Multifox antes de pasar al cuadro de pagos.' . ($comment ? " {$comment}" : ''));
            } else {
                $this->moveToPayments($cuenta, $user);
                $this->log($cuenta, CuentaCobroHistory::ACTION_SIN_MULTIFOX, $user, CuentaCobroWorkflow::STEP_MULTIFOX,
                    'No requiere Multifox. Pasa al cuadro de pagos.' . ($comment ? " {$comment}" : ''));
            }

            return $cuenta;
        });

        if (!$requiresMultifox) {
            $this->notifications->inPayments($cuenta, $comment);
        }

        return $cuenta;
    }

    /**
     * Paula carga la cuenta en Multifox (número y, si lo hay, el soporte): pasa al cuadro de pagos.
     * Se puede hacer directamente desde la revisión (implica que sí requería Multifox).
     */
    public function registerMultifox(CuentaCobro $cuenta, array $data, User $user): CuentaCobro
    {
        if (!$this->canHandleMultifox($cuenta, $user)) {
            throw new InvalidArgumentException('Esta cuenta de cobro no está pendiente de Multifox o no puedes gestionarla.');
        }

        $file = !empty($data['upload_token']) ? $this->storage->claimFile($data['upload_token'], $user, $cuenta->storage_path) : null;
        $comment = isset($data['comment']) ? (trim($data['comment']) ?: null) : null;

        $cuenta = DB::transaction(function () use ($cuenta, $data, $file, $comment, $user) {
            $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();
            if (!in_array($cuenta->status, CuentaCobroWorkflow::MULTIFOX_STATUSES, true)) {
                throw new InvalidArgumentException('Otro usuario ya gestionó esta cuenta de cobro.');
            }

            $cuenta->update([
                'requires_multifox' => true,
                'multifox_decided_by' => $cuenta->multifox_decided_by ?? $user->id,
                'multifox_decided_at' => $cuenta->multifox_decided_at ?? now(),
                'multifox_number' => trim($data['multifox_number']),
                'multifox_file_name' => $file['file_name'] ?? null,
                'multifox_file_path' => $file['file_path'] ?? null,
                'multifox_file_size' => $file['file_size'] ?? null,
                'multifox_file_mime' => $file['file_mime'] ?? null,
                'multifox_by' => $user->id,
                'multifox_at' => now(),
            ]);
            $this->moveToPayments($cuenta, $user);
            $this->log($cuenta, CuentaCobroHistory::ACTION_MULTIFOX, $user, CuentaCobroWorkflow::STEP_MULTIFOX,
                "Cargada en Multifox (N.º {$cuenta->multifox_number}). Pasa al cuadro de pagos." . ($comment ? " {$comment}" : ''), $file['file_name'] ?? null);

            return $cuenta;
        });

        $this->notifications->inPayments($cuenta, $comment);

        return $cuenta;
    }

    public function downloadUrl(CuentaCobro $cuenta, string $file, ?int $adjuntoId = null): string
    {
        if ($file === 'adjunto') {
            $adjunto = $cuenta->adjuntos()->findOrFail($adjuntoId);

            return $this->storage->downloadUrl($adjunto->file_path, $adjunto->file_name);
        }
        if ($file === 'multifox') {
            if (!$cuenta->multifox_file_path) {
                throw new InvalidArgumentException('Esta cuenta de cobro no tiene soporte de Multifox.');
            }

            return $this->storage->downloadUrl($cuenta->multifox_file_path, $cuenta->multifox_file_name);
        }

        return $this->storage->downloadUrl($cuenta->file_path, $cuenta->file_name);
    }

    /**
     * Estado de cada aprobación (ciclo actual) y del paso de Multifox, para la línea de tiempo.
     */
    public function progress(CuentaCobro $cuenta): array
    {
        $history = $cuenta->exists ? $cuenta->history()->with('user:id,name')->get() : collect();
        $cycleStart = $history->whereIn('action', [CuentaCobroHistory::ACTION_RADICADA, CuentaCobroHistory::ACTION_REENVIADA])->max('id') ?? 0;
        $cycle = $history->where('id', '>', $cycleStart);

        $steps = array_map(function (string $stage) use ($cuenta, $cycle) {
            $entry = $cycle->where('stage', $stage)->whereIn('action', [CuentaCobroHistory::ACTION_APROBADO, CuentaCobroHistory::ACTION_DEVUELTO])->last();

            return [
                'key' => $stage,
                'label' => CuentaCobroWorkflow::STAGES[$stage]['label'],
                'kind' => 'approval',
                'state' => match (true) {
                    $entry?->action === CuentaCobroHistory::ACTION_APROBADO => 'aprobado',
                    $entry?->action === CuentaCobroHistory::ACTION_DEVUELTO => 'devuelto',
                    $cuenta->current_stage === $stage => 'actual',
                    default => 'pendiente',
                },
                'user' => $entry?->user?->name,
                'at' => $entry?->created_at?->toIso8601String(),
            ];
        }, array_keys(CuentaCobroWorkflow::STAGES));

        $final = $history->whereIn('action', [CuentaCobroHistory::ACTION_SIN_MULTIFOX, CuentaCobroHistory::ACTION_MULTIFOX])->last();
        $steps[] = [
            'key' => CuentaCobroWorkflow::STEP_MULTIFOX,
            'label' => $cuenta->requires_multifox === false ? 'Sin Multifox' : 'Multifox',
            'kind' => 'document',
            'state' => match (true) {
                $cuenta->status === CuentaCobroWorkflow::STATUS_EN_PAGOS => 'aprobado',
                in_array($cuenta->status, CuentaCobroWorkflow::MULTIFOX_STATUSES, true) => 'actual',
                default => 'pendiente',
            },
            'user' => $final?->user?->name,
            'at' => $final?->created_at?->toIso8601String(),
        ];

        return $steps;
    }

    protected function moveToPayments(CuentaCobro $cuenta, User $user): void
    {
        $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_EN_PAGOS, 'completed_at' => now()]);
        $this->pagos->registerFromCuentaCobro($cuenta, $user);
    }

    protected function assertAttachments(CuentaCobroTipo $tipo, int $count): void
    {
        if ($tipo->requires_attachment && $count === 0) {
            throw new InvalidArgumentException("Las cuentas de cobro de {$tipo->name} requieren adjunto"
                . ($tipo->attachment_hint ? ": {$tipo->attachment_hint}." : '.'));
        }
    }

    protected function attach(CuentaCobro $cuenta, array $tokens, User $user): void
    {
        foreach ($tokens as $token) {
            $cuenta->adjuntos()->create([...$this->storage->claimFile($token, $user, $cuenta->storage_path), 'uploaded_by' => $user->id]);
        }
    }

    protected function accessibleProject(int $projectId, User $user): Project
    {
        $project = Project::find($projectId);
        if (!$project || !$user->canAccessProject($projectId)) {
            throw new InvalidArgumentException('No tienes acceso al proyecto seleccionado.');
        }

        return $project;
    }

    protected function fields(array $data): array
    {
        return [
            'tipo_id' => $data['tipo_id'],
            'project_id' => $data['project_id'],
            'tower_id' => $data['tower_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'contractor_id' => $data['contractor_id'],
            'document_number' => trim($data['document_number']),
            'amount' => $data['amount'],
            'description' => isset($data['description']) ? (trim($data['description']) ?: null) : null,
        ];
    }

    protected function log(CuentaCobro $cuenta, string $action, User $user, ?string $stage, ?string $comment, ?string $fileName = null): void
    {
        $cuenta->history()->create([
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment ? trim($comment) : null,
            'file_name' => $fileName,
            'user_id' => $user->id,
        ]);
    }

    protected function present(CuentaCobro $cuenta, User $user, bool $withDetail = false): array
    {
        $data = [
            'id' => $cuenta->id,
            'code' => $cuenta->code,
            'tipo_id' => $cuenta->tipo_id,
            'tipo' => $cuenta->tipo ? ['id' => $cuenta->tipo->id, 'name' => $cuenta->tipo->name, 'requires_attachment' => $cuenta->tipo->requires_attachment] : null,
            'project_id' => $cuenta->project_id,
            'project' => $cuenta->project,
            'tower_id' => $cuenta->tower_id,
            'tower' => $cuenta->tower,
            'contract_id' => $cuenta->contract_id,
            'contract' => $cuenta->contract,
            'contractor_id' => $cuenta->contractor_id,
            'contractor' => $cuenta->contractor,
            'document_number' => $cuenta->document_number,
            'amount' => (float) $cuenta->amount,
            'description' => $cuenta->description,
            'status' => $cuenta->status,
            'status_label' => CuentaCobroWorkflow::statusLabel($cuenta->status),
            'current_stage' => $cuenta->current_stage,
            'current_stage_label' => CuentaCobroWorkflow::stageLabel($cuenta->current_stage),
            'returned_stage_label' => CuentaCobroWorkflow::stageLabel($cuenta->returned_stage),
            'requires_multifox' => $cuenta->requires_multifox,
            'multifox_number' => $cuenta->multifox_number,
            'file_name' => $cuenta->file_name,
            'file_size' => $cuenta->file_size,
            'adjuntos_count' => $cuenta->adjuntos_count ?? null,
            'created_by' => $cuenta->creator ? ['id' => $cuenta->creator->id, 'name' => $cuenta->creator->name] : null,
            'created_at' => $cuenta->created_at?->toIso8601String(),
            'approved_at' => $cuenta->approved_at?->toIso8601String(),
            'completed_at' => $cuenta->completed_at?->toIso8601String(),
            'can_review' => $this->canReview($cuenta, $user),
            'can_resubmit' => $this->canResubmit($cuenta, $user),
            'can_handle_multifox' => $this->canHandleMultifox($cuenta, $user),
        ];

        if ($withDetail) {
            $data['tipo']['attachment_hint'] = $cuenta->tipo?->attachment_hint;
            $data['adjuntos'] = $cuenta->adjuntos->map(fn (CuentaCobroAdjunto $a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_size' => $a->file_size,
                'uploaded_by' => $a->uploader?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values();
            $data['multifox_file_name'] = $cuenta->multifox_file_name;
            $data['multifox_file_size'] = $cuenta->multifox_file_size;
            $data['multifox_by'] = $cuenta->multifoxUser?->name;
            $data['multifox_decided_by'] = $cuenta->multifoxDecider?->name;
            $data['progress'] = $this->progress($cuenta);
            $data['history'] = $cuenta->history->sortByDesc('id')->map(fn (CuentaCobroHistory $h) => [
                'id' => $h->id,
                'action' => $h->action,
                'stage' => $h->stage,
                'stage_label' => CuentaCobroWorkflow::stageLabel($h->stage),
                'comment' => $h->comment,
                'file_name' => $h->file_name,
                'user' => $h->user?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
