<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteHistory;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorteService
{
    public const SCOPE_MINE = 'mine';
    public const SCOPE_PENDING = 'pending';
    public const SCOPE_ALL = 'all';
    public const SCOPE_BILLING = 'billing'; // Cortes aprobados para cargar facturas (cortes.facturar)
    public const SCOPE_MULTIFOX = 'multifox'; // Cortes con facturas por confirmar en Multifox (Paula)

    public function __construct(
        protected CorteStorageService $storage,
        protected CorteNotificationService $notifications,
    ) {
    }

    protected function listRelations(): array
    {
        return [
            'project:id,name',
            'tower:id,name',
            'contractor:id,name,nit',
            'contract:id,contract_code',
            'creator:id,name',
        ];
    }

    /**
     * Catálogos del formulario: proyectos con sus torres, proveedores y contratos accesibles.
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

        $contracts = ContratacionContract::query()
            ->accessibleBy($user)
            ->orderBy('contract_code')
            ->get(['id', 'project_id', 'tower_id', 'contract_code', 'contractor_name']);

        // Números de corte ya usados en cada contrato, para la lista CORTE 1, CORTE 2...
        $usedNumbers = Corte::query()
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->get(['id', 'contract_id', 'code'])
            ->groupBy('contract_id')
            ->map(fn ($cortes) => $cortes->map(fn (Corte $c) => Corte::numberOf($c->code))->filter()->unique()->sort()->values());

        return [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'towers' => $project->towers->map(fn ($tower) => ['id' => $tower->id, 'name' => $tower->name])->values(),
            ])->values(),
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'contracts' => $contracts->map(fn (ContratacionContract $contract) => [
                ...$contract->only(['id', 'project_id', 'tower_id', 'contract_code', 'contractor_name']),
                'corte_numbers' => $usedNumbers->get($contract->id, collect())->all(),
            ])->values(),
            'max_upload_mb' => config('cortes.max_upload_mb'),
            'review_stages' => CorteWorkflow::stagesFor($user),
            'can_view_all' => $user->can(CorteWorkflow::PERMISSION_VIEW_ALL),
            'can_invoice' => $user->can(CorteWorkflow::PERMISSION_INVOICE),
            'can_confirm_multifox' => CorteWorkflow::canConfirmMultifox($user),
            'invoice_document_types' => collect(CorteInvoice::DOCUMENT_TYPES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            ...CorteWorkflow::toArray(),
        ];
    }

    /**
     * Listado paginado según la pestaña:
     * - mine: cortes que radicó el usuario (su histórico).
     * - pending: cortes que esperan una etapa que el usuario puede revisar.
     * - all: todos los cortes de sus proyectos (requiere cortes.ver_todos).
     * - billing: cortes aprobados o facturados, para cargar facturas (requiere cortes.facturar).
     */
    public function listCortes(User $user, string $scope, array $filters = [])
    {
        $query = $this->scopedQuery($user, $scope)->with($this->listRelations())->withCount('invoices');

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
                  ->orWhere('file_name', 'ilike', $search)
                  ->orWhereHas('contractor', fn ($sub) => $sub->where('name', 'ilike', $search))
                  ->orWhereHas('contract', fn ($sub) => $sub->where('contract_code', 'ilike', $search));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (Corte $corte) => $this->present($corte, $user));
    }

    /**
     * Totales para las pestañas del listado.
     */
    public function counts(User $user): array
    {
        return [
            self::SCOPE_MINE => $this->scopedQuery($user, self::SCOPE_MINE)->count(),
            self::SCOPE_PENDING => $this->scopedQuery($user, self::SCOPE_PENDING)->count(),
            // En facturación se cuentan los que aún no tienen factura
            self::SCOPE_BILLING => $user->can(CorteWorkflow::PERMISSION_INVOICE)
                ? $this->scopedQuery($user, self::SCOPE_BILLING)->where('status', CorteWorkflow::STATUS_APROBADO)->count()
                : 0,
            self::SCOPE_MULTIFOX => CorteWorkflow::canConfirmMultifox($user)
                ? $this->scopedQuery($user, self::SCOPE_MULTIFOX)->count()
                : 0,
        ];
    }

    protected function scopedQuery(User $user, string $scope): Builder
    {
        $query = Corte::query();

        if ($scope === self::SCOPE_PENDING) {
            $stages = CorteWorkflow::stagesFor($user);

            return $query->accessibleBy($user)->whereIn('current_stage', $stages ?: ['__none__']);
        }

        if ($scope === self::SCOPE_ALL && $user->can(CorteWorkflow::PERMISSION_VIEW_ALL)) {
            return $query->accessibleBy($user);
        }

        if ($scope === self::SCOPE_BILLING && $user->can(CorteWorkflow::PERMISSION_INVOICE)) {
            return $query->accessibleBy($user)->whereIn('status', CorteWorkflow::INVOICEABLE_STATUSES);
        }

        if ($scope === self::SCOPE_MULTIFOX && CorteWorkflow::canConfirmMultifox($user)) {
            return $query->accessibleBy($user)->whereHas('invoices', fn ($q) => $q->whereNull('multifox_confirmed_at'));
        }

        return $query->where('created_by', $user->id);
    }

    public function getCorte(Corte $corte, User $user): array
    {
        $corte->load([...$this->listRelations(), 'history.user:id,name', 'invoices.uploader:id,name', 'invoices.multifoxConfirmer:id,name']);

        return $this->present($corte, $user, true);
    }

    /**
     * El residente que lo radicó, los revisores de cualquier etapa con acceso al proyecto, quienes tienen
     * cortes.ver_todos y, una vez aprobado, quienes cargan facturas pueden consultar el corte.
     */
    public function canView(Corte $corte, User $user): bool
    {
        if ($corte->created_by === $user->id) {
            return true;
        }
        if (!$user->canAccessProject($corte->project_id)) {
            return false;
        }

        return $user->can(CorteWorkflow::PERMISSION_VIEW_ALL)
            || CorteWorkflow::stagesFor($user) !== []
            || $this->canInvoice($corte, $user)
            || ($corte->invoices()->exists() && CorteWorkflow::canConfirmMultifox($user));
    }

    /**
     * Facturas: solo con las tres aprobaciones y para quien tiene cortes.facturar en el proyecto.
     */
    public function canInvoice(Corte $corte, User $user): bool
    {
        return in_array($corte->status, CorteWorkflow::INVOICEABLE_STATUSES, true)
            && $user->can(CorteWorkflow::PERMISSION_INVOICE)
            && $user->canAccessProject($corte->project_id);
    }

    public function canReview(Corte $corte, User $user): bool
    {
        return $corte->current_stage !== null
            && in_array($corte->current_stage, CorteWorkflow::stagesFor($user), true)
            && $user->canAccessProject($corte->project_id);
    }

    public function canResubmit(Corte $corte, User $user): bool
    {
        return $corte->status === CorteWorkflow::STATUS_DEVUELTO && $corte->created_by === $user->id;
    }

    /**
     * Radica el corte con el archivo ya subido a S3 y lo deja pendiente del Interventor.
     */
    public function createCorte(array $data, User $user): Corte
    {
        $data['code'] = $this->normalizeCode($data['code']);
        $folder = $this->storage->folderFor($data['project_id'], $data['tower_id'] ?? null, $data['contract_id'], $data['code']);
        $file = $this->storage->claimCorteFile($data['upload_token'], $user, $folder);
        $firstStage = CorteWorkflow::firstStage();

        $corte = DB::transaction(function () use ($data, $file, $user, $firstStage, $folder) {
            $corte = Corte::create([
                'code' => $data['code'],
                'storage_path' => $folder,
                'project_id' => $data['project_id'],
                'tower_id' => $data['tower_id'] ?? null,
                'contractor_id' => $data['contractor_id'],
                'contract_id' => $data['contract_id'],
                'status' => CorteWorkflow::statusFor($firstStage),
                'current_stage' => $firstStage,
                'created_by' => $user->id,
                ...$file,
            ]);

            $this->log($corte, CorteHistory::ACTION_RADICADO, $user, null, $data['comment'] ?? null);

            return $corte;
        });

        // Los correos se envían después del commit para no avisar de un corte que no quedó guardado
        $this->notifications->submitted($corte, false, $data['comment'] ?? null);

        return $corte;
    }

    /**
     * Aprueba o devuelve el corte en la etapa actual.
     */
    public function review(Corte $corte, string $decision, ?string $comment, User $user): Corte
    {
        [$corte, $stage] = DB::transaction(function () use ($corte, $decision, $comment, $user) {
            // Bloqueo de fila: si dos revisores actúan a la vez, el segundo ve el estado ya actualizado
            $corte = Corte::whereKey($corte->id)->lockForUpdate()->firstOrFail();

            if (!$this->canReview($corte, $user)) {
                throw new InvalidArgumentException('Este corte ya no está pendiente de tu revisión.');
            }

            $stage = $corte->current_stage;

            if ($decision === 'aprobar') {
                $nextStage = CorteWorkflow::nextStage($stage);
                $corte->update([
                    'status' => $nextStage ? CorteWorkflow::statusFor($nextStage) : CorteWorkflow::STATUS_APROBADO,
                    'current_stage' => $nextStage,
                    'approved_at' => $nextStage ? null : now(),
                ]);
                $this->log($corte, CorteHistory::ACTION_APROBADO, $user, $stage, $comment);
            } else {
                $corte->update([
                    'status' => CorteWorkflow::STATUS_DEVUELTO,
                    'current_stage' => null,
                    'returned_stage' => $stage,
                ]);
                $this->log($corte, CorteHistory::ACTION_DEVUELTO, $user, $stage, $comment);
            }

            return [$corte, $stage];
        });

        $decision === 'aprobar'
            ? $this->notifications->approved($corte, $stage, $comment)
            : $this->notifications->returned($corte, $stage, (string) $comment);

        return $corte;
    }

    /**
     * El residente corrige un corte devuelto (datos y/o archivo) y lo reenvía: vuelve a Interventor.
     * El archivo anterior se conserva en S3 y queda referenciado en el historial.
     */
    public function resubmit(Corte $corte, array $data, User $user): Corte
    {
        if (!$this->canResubmit($corte, $user)) {
            throw new InvalidArgumentException('Solo el residente que radicó el corte puede reenviarlo cuando está devuelto.');
        }

        // Los cortes anteriores a la numeración pueden conservar su código libre
        $data['code'] = $data['code'] === $corte->code ? $corte->code : $this->normalizeCode($data['code']);

        // El archivo corregido queda en la carpeta del corte, junto al anterior. Si cambia el contrato o el número,
        // la carpeta pasa a ser la del nuevo contrato y el archivo vigente se lleva allá
        $folder = $this->storage->folderOf($corte);
        if ((int) $data['contract_id'] !== $corte->contract_id || $data['code'] !== $corte->code) {
            $folder = $this->storage->folderFor($data['project_id'], $data['tower_id'] ?? null, $data['contract_id'], $data['code']);
        }

        $file = !empty($data['upload_token'])
            ? $this->storage->claimCorteFile($data['upload_token'], $user, $folder)
            : ($folder !== $corte->storage_path ? $this->movedFile($corte, $folder) : []);
        $firstStage = CorteWorkflow::firstStage();

        $corte = DB::transaction(function () use ($corte, $data, $file, $user, $firstStage, $folder) {
            $corte->update([
                'code' => $data['code'],
                'storage_path' => $folder,
                'project_id' => $data['project_id'],
                'tower_id' => $data['tower_id'] ?? null,
                'contractor_id' => $data['contractor_id'],
                'contract_id' => $data['contract_id'],
                'status' => CorteWorkflow::statusFor($firstStage),
                'current_stage' => $firstStage,
                ...$file,
            ]);

            $this->log($corte, CorteHistory::ACTION_REENVIADO, $user, null, $data['comment'] ?? null);

            return $corte;
        });

        $this->notifications->submitted($corte, true, $data['comment'] ?? null);

        return $corte;
    }

    /**
     * @return array{file_path: string, file_name: string}
     */
    protected function movedFile(Corte $corte, string $folder): array
    {
        $path = $this->storage->moveCorteFile($corte, $folder);

        return ['file_path' => $path, 'file_name' => basename($path)];
    }

    /**
     * "3", "corte 3" o "CORTE 3" -> "CORTE 3".
     */
    protected function normalizeCode(string $code): string
    {
        $number = Corte::numberOf($code);
        if ($number === null) {
            throw new InvalidArgumentException('Elige el número del corte (CORTE 1, CORTE 2...).');
        }

        return Corte::codeFor($number);
    }

    public function downloadUrl(Corte $corte): string
    {
        return $this->storage->downloadUrl($corte);
    }

    protected function log(Corte $corte, string $action, User $user, ?string $stage, ?string $comment): void
    {
        $corte->history()->create([
            'stage' => $stage,
            'action' => $action,
            'comment' => $comment ? trim($comment) : null,
            'file_name' => $corte->file_name,
            'file_path' => $corte->file_path,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Estado de cada etapa en el ciclo actual (desde la última radicación o reenvío), para la línea de tiempo.
     */
    public function progress(Corte $corte): array
    {
        $cycleStartId = $corte->history
            ->whereIn('action', [CorteHistory::ACTION_RADICADO, CorteHistory::ACTION_REENVIADO])
            ->max('id') ?? 0;
        $cycle = $corte->history->filter(fn (CorteHistory $h) => $h->id > $cycleStartId);

        return array_map(function (string $stage) use ($corte, $cycle) {
            $entry = $cycle->where('stage', $stage)->last();

            $state = match (true) {
                $entry?->action === CorteHistory::ACTION_APROBADO => 'aprobado',
                $entry?->action === CorteHistory::ACTION_DEVUELTO => 'devuelto',
                $corte->current_stage === $stage => 'actual',
                default => 'pendiente',
            };

            return [
                'key' => $stage,
                'label' => CorteWorkflow::stageLabel($stage),
                'state' => $state,
                'user' => $entry?->user?->name,
                'at' => $entry?->created_at?->toIso8601String(),
            ];
        }, array_keys(CorteWorkflow::STAGES));
    }

    protected function present(Corte $corte, User $user, bool $withHistory = false): array
    {
        $data = [
            'id' => $corte->id,
            'code' => $corte->code,
            'label' => $corte->label(),
            'project_id' => $corte->project_id,
            'project' => $corte->project,
            'tower_id' => $corte->tower_id,
            'tower' => $corte->tower,
            'contractor_id' => $corte->contractor_id,
            'contractor' => $corte->contractor,
            'contract_id' => $corte->contract_id,
            'contract' => $corte->contract,
            'status' => $corte->status,
            'status_label' => CorteWorkflow::statusLabel($corte->status),
            'current_stage' => $corte->current_stage,
            'current_stage_label' => CorteWorkflow::stageLabel($corte->current_stage),
            'returned_stage_label' => CorteWorkflow::stageLabel($corte->returned_stage),
            'file_name' => $corte->file_name,
            'file_size' => $corte->file_size,
            'created_by' => $corte->creator ? ['id' => $corte->creator->id, 'name' => $corte->creator->name] : null,
            'created_at' => $corte->created_at?->toIso8601String(),
            'updated_at' => $corte->updated_at?->toIso8601String(),
            'approved_at' => $corte->approved_at?->toIso8601String(),
            'can_review' => $this->canReview($corte, $user),
            'can_resubmit' => $this->canResubmit($corte, $user),
            'can_invoice' => $this->canInvoice($corte, $user),
            'invoices_count' => $corte->invoices_count ?? ($corte->relationLoaded('invoices') ? $corte->invoices->count() : 0),
        ];

        if ($withHistory) {
            $data['progress'] = $this->progress($corte);
            $data['history'] = $corte->history->sortByDesc('id')->map(fn (CorteHistory $h) => [
                'id' => $h->id,
                'action' => $h->action,
                'stage' => $h->stage,
                'stage_label' => CorteWorkflow::stageLabel($h->stage),
                'comment' => $h->comment,
                'file_name' => $h->file_name,
                'user' => $h->user?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values();
            $data['invoices'] = $corte->invoices->map(fn (CorteInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
                'document_type_label' => $invoice->documentTypeLabel(),
                'related_document' => $invoice->related_document,
                'observation' => $invoice->observation,
                'amount' => $invoice->amount !== null ? (float) $invoice->amount : null,
                'multifox_confirmed_at' => $invoice->multifox_confirmed_at?->toIso8601String(),
                'multifox_confirmed_by' => $invoice->multifoxConfirmer?->name,
                'can_confirm_multifox' => !$invoice->multifox_confirmed_at && $invoice->amount !== null && CorteWorkflow::canConfirmMultifox($user),
                'file_name' => $invoice->file_name,
                'file_size' => $invoice->file_size,
                'uploaded_by' => $invoice->uploader?->name,
                'created_at' => $invoice->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
