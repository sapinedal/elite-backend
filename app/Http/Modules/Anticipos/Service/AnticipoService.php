<?php

namespace App\Http\Modules\Anticipos\Service;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Models\AnticipoHistory;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Anticipos de contratos:
 *
 *   Residente radica la cuenta de cobro -> Director de Obra -> Gerencia -> Contabilidad genera el anticipo
 *   en Multifox y carga el soporte -> Cuadro de pagos
 */
class AnticipoService
{
    public const SCOPE_MINE = 'mine';
    public const SCOPE_PENDING = 'pending';   // Anticipos que esperan una aprobación del usuario
    public const SCOPE_ACCOUNTING = 'accounting'; // Aprobados que Contabilidad debe generar en Multifox
    public const SCOPE_ALL = 'all';

    public function __construct(
        protected AnticipoStorageService $storage,
        protected AnticipoNotificationService $notifications,
        protected PagoService $pagos,
    ) {
    }

    protected function listRelations(): array
    {
        return [
            'project:id,name',
            'tower:id,name',
            'contract:id,contract_code,contractor_name',
            'contractor:id,name,nit',
            'creator:id,name',
        ];
    }

    /**
     * Catálogos del formulario: contratos accesibles (con su proyecto y frente), proveedores y lo que el usuario puede hacer.
     */
    public function catalogs(User $user): array
    {
        $contracts = ContratacionContract::query()
            ->accessibleBy($user)
            ->with(['project:id,name', 'tower:id,name'])
            ->orderBy('contract_code')
            ->get(['id', 'project_id', 'tower_id', 'contract_code', 'contractor_name', 'object']);

        return [
            'contracts' => $contracts->map(fn (ContratacionContract $c) => [
                'id' => $c->id,
                'contract_code' => $c->contract_code,
                'contractor_name' => $c->contractor_name,
                'object' => $c->object,
                'project_id' => $c->project_id,
                'project' => $c->project?->name,
                'tower' => $c->tower?->name,
            ])->values(),
            'projects' => $contracts->pluck('project')->filter()->unique('id')->sortBy('name')
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'max_upload_mb' => config('anticipos.max_upload_mb'),
            'review_stages' => AnticipoWorkflow::stagesFor($user),
            'can_view_all' => $user->can(AnticipoWorkflow::PERMISSION_VIEW_ALL),
            'can_create' => $user->can('anticipos.crear'),
            'can_contabilizar' => AnticipoWorkflow::canContabilizar($user),
            ...AnticipoWorkflow::toArray(),
        ];
    }

    public function listAnticipos(User $user, string $scope, array $filters = [])
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
                  ->orWhere('document_number', 'ilike', $search)
                  ->orWhere('multifox_number', 'ilike', $search)
                  ->orWhereHas('contract', fn ($sub) => $sub->where('contract_code', 'ilike', $search)->orWhere('contractor_name', 'ilike', $search))
                  ->orWhereHas('contractor', fn ($sub) => $sub->where('name', 'ilike', $search)->orWhere('nit', 'ilike', $search));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (Anticipo $anticipo) => $this->present($anticipo, $user));
    }

    public function counts(User $user): array
    {
        return [
            self::SCOPE_MINE => $this->scopedQuery($user, self::SCOPE_MINE)->count(),
            self::SCOPE_PENDING => $this->scopedQuery($user, self::SCOPE_PENDING)->count(),
            self::SCOPE_ACCOUNTING => AnticipoWorkflow::canContabilizar($user) ? $this->scopedQuery($user, self::SCOPE_ACCOUNTING)->count() : 0,
        ];
    }

    protected function scopedQuery(User $user, string $scope): Builder
    {
        $query = Anticipo::query();

        if ($scope === self::SCOPE_PENDING) {
            return $query->accessibleBy($user)->whereIn('current_stage', AnticipoWorkflow::stagesFor($user) ?: ['__none__']);
        }

        if ($scope === self::SCOPE_ACCOUNTING && AnticipoWorkflow::canContabilizar($user)) {
            return $query->accessibleBy($user)->where('status', AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD);
        }

        if ($scope === self::SCOPE_ALL && ($user->can(AnticipoWorkflow::PERMISSION_VIEW_ALL) || AnticipoWorkflow::canContabilizar($user))) {
            return $query->accessibleBy($user);
        }

        return $query->where('created_by', $user->id);
    }

    public function getAnticipo(Anticipo $anticipo, User $user): array
    {
        $anticipo->load([...$this->listRelations(), 'history.user:id,name', 'multifoxUser:id,name']);

        return $this->present($anticipo, $user, true);
    }

    /**
     * El residente que lo radicó, los aprobadores, Contabilidad y quienes tienen anticipos.ver_todos
     * (con acceso al proyecto) pueden consultarlo.
     */
    public function canView(Anticipo $anticipo, User $user): bool
    {
        if ($anticipo->created_by === $user->id) {
            return true;
        }
        if (!$user->canAccessProject($anticipo->project_id)) {
            return false;
        }

        return $user->can(AnticipoWorkflow::PERMISSION_VIEW_ALL)
            || AnticipoWorkflow::stagesFor($user) !== []
            || AnticipoWorkflow::canContabilizar($user);
    }

    public function canReview(Anticipo $anticipo, User $user): bool
    {
        return $anticipo->current_stage !== null
            && in_array($anticipo->current_stage, AnticipoWorkflow::stagesFor($user), true)
            && $user->canAccessProject($anticipo->project_id);
    }

    public function canResubmit(Anticipo $anticipo, User $user): bool
    {
        return $anticipo->status === AnticipoWorkflow::STATUS_DEVUELTO && $anticipo->created_by === $user->id;
    }

    public function canRegisterMultifox(Anticipo $anticipo, User $user): bool
    {
        return $anticipo->status === AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD
            && AnticipoWorkflow::canContabilizar($user)
            && $user->canAccessProject($anticipo->project_id);
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->storage->requestUpload($fileName, $fileSize, $mime, $user);
    }

    /**
     * Radica el anticipo con la cuenta de cobro ya subida a S3 y lo deja pendiente del Director de Obra.
     * El proyecto y el frente salen del contrato.
     */
    public function createAnticipo(array $data, User $user): Anticipo
    {
        $contract = $this->accessibleContract($data['contract_id'], $user);
        $firstStage = AnticipoWorkflow::firstStage();

        // El código se reserva antes de mover el archivo: la carpeta del anticipo lleva su código
        $anticipo = DB::transaction(function () use ($data, $user, $contract, $firstStage) {
            $anticipo = Anticipo::create([
                ...$this->fields($data, $contract),
                'status' => AnticipoWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
                'created_by' => $user->id,
                'file_name' => '',
                'file_path' => '',
            ]);
            $anticipo->update(['code' => 'ANT-' . str_pad((string) $anticipo->id, 4, '0', STR_PAD_LEFT)]);

            $folder = $this->storage->folderFor($contract, $anticipo->code);
            $anticipo->update(['storage_path' => $folder, ...$this->storage->claimFile($data['upload_token'], $user, $folder)]);

            $this->log($anticipo, AnticipoHistory::ACTION_RADICADO, $user, null, $data['comment'] ?? null, $anticipo);

            return $anticipo;
        });

        // Los correos se envían después del commit para no avisar de un anticipo que no quedó guardado
        $this->notifications->submitted($anticipo, false, $data['comment'] ?? null);

        return $anticipo;
    }

    /**
     * Aprueba o devuelve el anticipo en la etapa actual. Con la última aprobación pasa a Contabilidad.
     */
    public function review(Anticipo $anticipo, string $decision, ?string $comment, User $user): Anticipo
    {
        [$anticipo, $stage] = DB::transaction(function () use ($anticipo, $decision, $comment, $user) {
            // Bloqueo de fila: si dos aprobadores actúan a la vez, el segundo ve el estado ya actualizado
            $anticipo = Anticipo::whereKey($anticipo->id)->lockForUpdate()->firstOrFail();

            if (!$this->canReview($anticipo, $user)) {
                throw new InvalidArgumentException('Este anticipo ya no está pendiente de tu aprobación.');
            }

            $stage = $anticipo->current_stage;

            if ($decision === 'aprobar') {
                $nextStage = AnticipoWorkflow::nextStage($stage);
                $anticipo->update($nextStage
                    ? ['status' => AnticipoWorkflow::STAGES[$nextStage]['status'], 'current_stage' => $nextStage]
                    : ['status' => AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD, 'current_stage' => null, 'approved_at' => now()]);
                $this->log($anticipo, AnticipoHistory::ACTION_APROBADO, $user, $stage, $comment);
            } else {
                $anticipo->update([
                    'status' => AnticipoWorkflow::STATUS_DEVUELTO,
                    'current_stage' => null,
                    'returned_stage' => $stage,
                ]);
                $this->log($anticipo, AnticipoHistory::ACTION_DEVUELTO, $user, $stage, $comment);
            }

            return [$anticipo, $stage];
        });

        $decision === 'aprobar'
            ? $this->notifications->approved($anticipo, $stage, $comment)
            : $this->notifications->returned($anticipo, $stage, (string) $comment);

        return $anticipo;
    }

    /**
     * El residente corrige un anticipo devuelto (datos y/o cuenta de cobro) y lo reenvía: vuelve al Director de Obra.
     * Si cambia el contrato, la carpeta pasa a ser la del nuevo contrato y la cuenta de cobro vigente se lleva allá.
     */
    public function resubmit(Anticipo $anticipo, array $data, User $user): Anticipo
    {
        if (!$this->canResubmit($anticipo, $user)) {
            throw new InvalidArgumentException('Solo el residente que radicó el anticipo puede reenviarlo cuando está devuelto.');
        }

        $contract = $this->accessibleContract($data['contract_id'], $user);
        $folder = $contract->id === $anticipo->contract_id && $anticipo->storage_path
            ? $anticipo->storage_path
            : $this->storage->folderFor($contract, $anticipo->code);

        $file = [];
        if (!empty($data['upload_token'])) {
            $file = $this->storage->claimFile($data['upload_token'], $user, $folder);
        } elseif ($folder !== $anticipo->storage_path) {
            $path = $this->storage->moveTo($anticipo->file_path, $folder);
            $file = ['file_path' => $path, 'file_name' => basename($path)];
        }

        $firstStage = AnticipoWorkflow::firstStage();

        $anticipo = DB::transaction(function () use ($anticipo, $data, $contract, $folder, $file, $user, $firstStage) {
            $anticipo->update([
                ...$this->fields($data, $contract),
                'storage_path' => $folder,
                'status' => AnticipoWorkflow::STAGES[$firstStage]['status'],
                'current_stage' => $firstStage,
                ...$file,
            ]);

            $this->log($anticipo, AnticipoHistory::ACTION_REENVIADO, $user, null, $data['comment'] ?? null, $file ? $anticipo : null);

            return $anticipo;
        });

        $this->notifications->submitted($anticipo, true, $data['comment'] ?? null);

        return $anticipo;
    }

    /**
     * Contabilidad carga el anticipo generado en Multifox (número y soporte): queda completado y pasa al cuadro de pagos.
     */
    public function registerMultifox(Anticipo $anticipo, array $data, User $user): Anticipo
    {
        if (!$this->canRegisterMultifox($anticipo, $user)) {
            throw new InvalidArgumentException('Este anticipo no está pendiente de Contabilidad o no puedes gestionarlo.');
        }

        $file = $this->storage->claimFile($data['upload_token'], $user, $anticipo->storage_path);
        $comment = isset($data['comment']) ? (trim($data['comment']) ?: null) : null;

        $anticipo = DB::transaction(function () use ($anticipo, $data, $file, $comment, $user) {
            $anticipo = Anticipo::whereKey($anticipo->id)->lockForUpdate()->firstOrFail();
            if ($anticipo->status !== AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD) {
                throw new InvalidArgumentException('Otro usuario ya cargó el anticipo de Multifox.');
            }

            $anticipo->update([
                'status' => AnticipoWorkflow::STATUS_COMPLETADO,
                'multifox_number' => trim($data['multifox_number']),
                'multifox_file_name' => $file['file_name'],
                'multifox_file_path' => $file['file_path'],
                'multifox_file_size' => $file['file_size'],
                'multifox_file_mime' => $file['file_mime'],
                'multifox_by' => $user->id,
                'multifox_at' => now(),
            ]);

            $anticipo->history()->create([
                'stage' => AnticipoWorkflow::STEP_CONTABILIDAD,
                'action' => AnticipoHistory::ACTION_MULTIFOX,
                'comment' => "Anticipo N.º {$anticipo->multifox_number} generado en Multifox. Pasa al cuadro de pagos." . ($comment ? " {$comment}" : ''),
                'file_name' => $file['file_name'],
                'file_path' => $file['file_path'],
                'user_id' => $user->id,
            ]);

            $this->pagos->registerFromAnticipo($anticipo, $user);

            return $anticipo;
        });

        $this->notifications->completed($anticipo, $comment);

        return $anticipo;
    }

    public function downloadUrl(Anticipo $anticipo, bool $multifox = false): string
    {
        if ($multifox && !$anticipo->multifox_file_path) {
            throw new InvalidArgumentException('Este anticipo aún no tiene el soporte de Multifox.');
        }

        return $multifox
            ? $this->storage->downloadUrl($anticipo->multifox_file_path, $anticipo->multifox_file_name)
            : $this->storage->downloadUrl($anticipo->file_path, $anticipo->file_name);
    }

    /**
     * Estado de cada aprobación (ciclo actual) y del paso de Contabilidad, para la línea de tiempo.
     */
    public function progress(Anticipo $anticipo): array
    {
        $history = $anticipo->exists ? $anticipo->history()->with('user:id,name')->get() : collect();
        $cycleStart = $history->whereIn('action', [AnticipoHistory::ACTION_RADICADO, AnticipoHistory::ACTION_REENVIADO])->max('id') ?? 0;
        $cycle = $history->where('id', '>', $cycleStart);

        $stages = array_map(function (string $stage) use ($anticipo, $cycle) {
            $entry = $cycle->where('stage', $stage)->whereIn('action', [AnticipoHistory::ACTION_APROBADO, AnticipoHistory::ACTION_DEVUELTO])->last();

            return [
                'key' => $stage,
                'label' => AnticipoWorkflow::STAGES[$stage]['label'],
                'kind' => 'approval',
                'state' => match (true) {
                    $entry?->action === AnticipoHistory::ACTION_APROBADO => 'aprobado',
                    $entry?->action === AnticipoHistory::ACTION_DEVUELTO => 'devuelto',
                    $anticipo->current_stage === $stage => 'actual',
                    default => 'pendiente',
                },
                'user' => $entry?->user?->name,
                'at' => $entry?->created_at?->toIso8601String(),
            ];
        }, array_keys(AnticipoWorkflow::STAGES));

        $multifox = $history->firstWhere('action', AnticipoHistory::ACTION_MULTIFOX);
        $stages[] = [
            'key' => AnticipoWorkflow::STEP_CONTABILIDAD,
            'label' => 'Anticipo Multifox',
            'kind' => 'document',
            'state' => match (true) {
                $anticipo->status === AnticipoWorkflow::STATUS_COMPLETADO => 'aprobado',
                $anticipo->status === AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD => 'actual',
                default => 'pendiente',
            },
            'user' => $multifox?->user?->name,
            'at' => $multifox?->created_at?->toIso8601String(),
        ];

        return $stages;
    }

    protected function accessibleContract(int $contractId, User $user): ContratacionContract
    {
        $contract = ContratacionContract::query()->accessibleBy($user)->find($contractId);
        if (!$contract) {
            throw new InvalidArgumentException('No tienes acceso al contrato seleccionado.');
        }

        return $contract;
    }

    protected function fields(array $data, ContratacionContract $contract): array
    {
        return [
            'contract_id' => $contract->id,
            'project_id' => $contract->project_id,
            'tower_id' => $contract->tower_id,
            'contractor_id' => $data['contractor_id'],
            'document_number' => trim($data['document_number']),
            'amount' => $data['amount'],
            'description' => isset($data['description']) ? (trim($data['description']) ?: null) : null,
        ];
    }

    protected function log(Anticipo $anticipo, string $action, User $user, ?string $stage, ?string $comment, ?Anticipo $withFile = null): void
    {
        $anticipo->history()->create([
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment ? trim($comment) : null,
            'file_name' => $withFile?->file_name,
            'file_path' => $withFile?->file_path,
            'user_id' => $user->id,
        ]);
    }

    protected function present(Anticipo $anticipo, User $user, bool $withDetail = false): array
    {
        $data = [
            'id' => $anticipo->id,
            'code' => $anticipo->code,
            'contract_id' => $anticipo->contract_id,
            'contract' => $anticipo->contract ? [
                'id' => $anticipo->contract->id,
                'contract_code' => $anticipo->contract->contract_code,
                'contractor_name' => $anticipo->contract->contractor_name,
            ] : null,
            'project_id' => $anticipo->project_id,
            'project' => $anticipo->project,
            'tower' => $anticipo->tower,
            'contractor_id' => $anticipo->contractor_id,
            'contractor' => $anticipo->contractor,
            'document_number' => $anticipo->document_number,
            'amount' => (float) $anticipo->amount,
            'description' => $anticipo->description,
            'status' => $anticipo->status,
            'status_label' => AnticipoWorkflow::statusLabel($anticipo->status),
            'current_stage' => $anticipo->current_stage,
            'current_stage_label' => AnticipoWorkflow::stageLabel($anticipo->current_stage),
            'returned_stage_label' => AnticipoWorkflow::stageLabel($anticipo->returned_stage),
            'file_name' => $anticipo->file_name,
            'file_size' => $anticipo->file_size,
            'multifox_number' => $anticipo->multifox_number,
            'created_by' => $anticipo->creator ? ['id' => $anticipo->creator->id, 'name' => $anticipo->creator->name] : null,
            'created_at' => $anticipo->created_at?->toIso8601String(),
            'approved_at' => $anticipo->approved_at?->toIso8601String(),
            'multifox_at' => $anticipo->multifox_at?->toIso8601String(),
            'can_review' => $this->canReview($anticipo, $user),
            'can_resubmit' => $this->canResubmit($anticipo, $user),
            'can_register_multifox' => $this->canRegisterMultifox($anticipo, $user),
        ];

        if ($withDetail) {
            $data['storage_path'] = $anticipo->storage_path;
            $data['multifox_file_name'] = $anticipo->multifox_file_name;
            $data['multifox_file_size'] = $anticipo->multifox_file_size;
            $data['multifox_by'] = $anticipo->multifoxUser?->name;
            $data['progress'] = $this->progress($anticipo);
            $data['history'] = $anticipo->history->sortByDesc('id')->map(fn (AnticipoHistory $h) => [
                'id' => $h->id,
                'action' => $h->action,
                'stage' => $h->stage,
                'stage_label' => AnticipoWorkflow::stageLabel($h->stage),
                'comment' => $h->comment,
                'file_name' => $h->file_name,
                'user' => $h->user?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
