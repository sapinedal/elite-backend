<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroAdjunto;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroDocument;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroHistory;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Pagos\Service\AccountingApprovalNotifier;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cuentas de cobro:
 *
 *   Radicada (con adjuntos si el tipo los exige) -> Director de Obra -> Gerencia -> Paula decide:
 *     - no requiere Multifox: pasa a la aprobación de Contabilidad
 *     - requiere Multifox: pasa a la aprobación de Contabilidad cuando se carga en Multifox
 *   Contabilidad la aprueba (cuadro de pagos) o la devuelve a Paula.
 *
 * Las directas (con frente) después de Gerencia siguen: Cargue ERP -> Factura (Facturas del mes) -> Liquidación ERP
 * -> Contabilidad (si la devuelve, vuelve a la liquidación) -> cuadro de pagos.
 */
class CuentaCobroService
{
    public const SCOPE_MINE = 'mine';
    public const SCOPE_PENDING = 'pending';   // Esperan una aprobación del usuario
    public const SCOPE_MULTIFOX = 'multifox'; // Aprobadas que Paula debe revisar / cargar en Multifox
    public const SCOPE_ACCOUNTING_APPROVAL = 'accounting_approval'; // Por aprobar en Contabilidad
    public const SCOPE_ERP = 'erp'; // Directas esperando el cargue ERP o la liquidación (cuentas_cobro.cargar_erp)
    public const SCOPE_ALL = 'all';

    // Pasos que se cargan en el módulo (la factura se asocia desde Facturas del mes)
    public const UPLOADABLE_STEPS = [CuentaCobroWorkflow::STEP_ERP, CuentaCobroWorkflow::STEP_LIQUIDACION];

    public function __construct(
        protected CuentaCobroStorageService $storage,
        protected CuentaCobroNotificationService $notifications,
        protected CuentaCobroTipoService $tipos,
        protected PagoService $pagos,
        protected AccountingApprovalNotifier $accounting,
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
            'can_approve_accounting' => CuentaCobroWorkflow::canApproveAccounting($user),
            'can_load_erp' => CuentaCobroWorkflow::canLoadErp($user),
            'can_parametrize' => $user->can(CuentaCobroWorkflow::PERMISSION_PARAMETRIZAR),
            // Crear un tipo nuevo desde el formulario de radicación
            'can_create_tipo' => $user->can(CuentaCobroWorkflow::PERMISSION_PARAMETRIZAR) || $user->can('cuentas_cobro.crear'),
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
            self::SCOPE_ACCOUNTING_APPROVAL => CuentaCobroWorkflow::canApproveAccounting($user) ? $this->scopedQuery($user, self::SCOPE_ACCOUNTING_APPROVAL)->count() : 0,
            self::SCOPE_ERP => CuentaCobroWorkflow::canLoadErp($user) ? $this->scopedQuery($user, self::SCOPE_ERP)->count() : 0,
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

        if ($scope === self::SCOPE_ACCOUNTING_APPROVAL && CuentaCobroWorkflow::canApproveAccounting($user)) {
            return $query->accessibleBy($user)->where('status', CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE);
        }

        if ($scope === self::SCOPE_ERP && CuentaCobroWorkflow::canLoadErp($user)) {
            return $query->accessibleBy($user)->whereIn('current_step', self::UPLOADABLE_STEPS);
        }

        if ($scope === self::SCOPE_ALL && ($user->can(CuentaCobroWorkflow::PERMISSION_VIEW_ALL) || CuentaCobroWorkflow::canHandleMultifox($user) || CuentaCobroWorkflow::canApproveAccounting($user) || CuentaCobroWorkflow::canLoadErp($user))) {
            return $query->accessibleBy($user);
        }

        return $query->where('created_by', $user->id);
    }

    public function getCuenta(CuentaCobro $cuenta, User $user): array
    {
        $cuenta->load([...$this->listRelations(), 'tipo', 'history.user:id,name', 'adjuntos.uploader:id,name', 'documents.uploader:id,name', 'multifoxUser:id,name', 'multifoxDecider:id,name']);

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
            || CuentaCobroWorkflow::canHandleMultifox($user)
            || CuentaCobroWorkflow::canApproveAccounting($user)
            || CuentaCobroWorkflow::canLoadErp($user);
    }

    /**
     * Cargue ERP y liquidación de las directas: quien radicó la cuenta o quien tenga cuentas_cobro.cargar_erp.
     */
    public function canUploadStep(CuentaCobro $cuenta, User $user): bool
    {
        return in_array($cuenta->current_step, self::UPLOADABLE_STEPS, true)
            && ($cuenta->created_by === $user->id || CuentaCobroWorkflow::canLoadErp($user))
            && $user->canAccessProject($cuenta->project_id);
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

    public function canReviewAccounting(CuentaCobro $cuenta, User $user): bool
    {
        return $cuenta->status === CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE
            && CuentaCobroWorkflow::canApproveAccounting($user)
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
                // Con la última aprobación: la directa pasa al cargue ERP; la de General / Indirectos, a Multifox
                $firstStep = CuentaCobroWorkflow::firstStep();
                $cuenta->update(match (true) {
                    (bool) $nextStage => ['status' => CuentaCobroWorkflow::STAGES[$nextStage]['status'], 'current_stage' => $nextStage],
                    $cuenta->isDirecta() => ['status' => CuentaCobroWorkflow::STEPS[$firstStep]['status'], 'current_stage' => null, 'current_step' => $firstStep, 'approved_at' => now()],
                    default => ['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_REVISION, 'current_stage' => null, 'approved_at' => now()],
                });
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
                'current_step' => null,
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
     * Paula decide si la cuenta requiere Multifox. Si no, pasa a la aprobación de Contabilidad; si sí, queda pendiente del cargue.
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
                $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_SIN_MULTIFOX, $user, CuentaCobroWorkflow::STEP_MULTIFOX,
                    'No requiere Multifox. Pendiente de aprobación de Contabilidad.' . ($comment ? " {$comment}" : ''));
            }

            return $cuenta;
        });

        if (!$requiresMultifox) {
            $this->accounting->pending('cuentas_cobro', $cuenta->project_id, [
                ...$this->accountingPayload($cuenta),
                'pending_intro' => "Paula indicó que la cuenta de cobro {$cuenta->reference()} no requiere Multifox.",
            ], $comment);
        }

        return $cuenta;
    }

    /**
     * Paula carga la cuenta en Multifox (número y, si lo hay, el soporte): pasa a la aprobación de Contabilidad.
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
            $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE]);
            $this->log($cuenta, CuentaCobroHistory::ACTION_MULTIFOX, $user, CuentaCobroWorkflow::STEP_MULTIFOX,
                "Cargada en Multifox (N.º {$cuenta->multifox_number}). Pendiente de aprobación de Contabilidad." . ($comment ? " {$comment}" : ''), $file['file_name'] ?? null);

            return $cuenta;
        });

        $this->accounting->pending('cuentas_cobro', $cuenta->project_id, $this->accountingPayload($cuenta), $comment);

        return $cuenta;
    }

    /**
     * Contabilidad aprueba la cuenta (pasa al cuadro de pagos) o la devuelve a Paula: si se había cargado en Multifox
     * vuelve al cargue (con el número y el soporte limpios); si no requería Multifox, vuelve a la revisión.
     */
    public function reviewAccounting(CuentaCobro $cuenta, string $decision, ?string $comment, User $user): CuentaCobro
    {
        $comment = $comment !== null ? (trim($comment) ?: null) : null;
        if (!$this->canReviewAccounting($cuenta, $user)) {
            throw new InvalidArgumentException('Esta cuenta de cobro no está pendiente de la aprobación de Contabilidad o no puedes aprobarla.');
        }
        if ($decision === 'devolver' && !$comment) {
            throw new InvalidArgumentException('Indica el motivo de la devolución para que sepan qué corregir.');
        }

        $multifoxEmail = ($cuenta->multifoxUser ?? $cuenta->multifoxDecider)?->email;
        $liquidacionEmail = $cuenta->documents()->where('step', CuentaCobroWorkflow::STEP_LIQUIDACION)->with('uploader:id,email')->latest('id')->first()?->uploader?->email;

        $cuenta = DB::transaction(function () use ($cuenta, $decision, $comment, $user) {
            $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();
            if ($cuenta->status !== CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE) {
                throw new InvalidArgumentException('Otro usuario ya revisó esta cuenta de cobro.');
            }

            if ($decision === 'aprobar') {
                $this->moveToPayments($cuenta, $user);
                $this->log($cuenta, CuentaCobroHistory::ACTION_CONTABILIDAD_APROBADA, $user, CuentaCobroWorkflow::STEP_APROBACION_CONTABLE,
                    'Aprobada por Contabilidad. Pasa al cuadro de pagos.' . ($comment ? " {$comment}" : ''));
            } elseif ($cuenta->isDirecta() && $cuenta->documents()->where('step', CuentaCobroWorkflow::STEP_LIQUIDACION)->exists()) {
                // Directa: vuelve a la liquidación ERP; el documento anterior queda en el historial (y su archivo en S3)
                $cuenta->documents()->where('step', CuentaCobroWorkflow::STEP_LIQUIDACION)->delete();
                $cuenta->update(['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_LIQUIDACION, 'current_step' => CuentaCobroWorkflow::STEP_LIQUIDACION]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_CONTABILIDAD_DEVUELTA, $user, CuentaCobroWorkflow::STEP_APROBACION_CONTABLE,
                    "Devuelta por Contabilidad a la liquidación ERP. {$comment}");
            } else {
                $loaded = $cuenta->requires_multifox && $cuenta->multifox_number;
                $cuenta->update($loaded
                    ? [
                        'status' => CuentaCobroWorkflow::STATUS_PENDIENTE_MULTIFOX,
                        'multifox_number' => null,
                        'multifox_file_name' => null,
                        'multifox_file_path' => null,
                        'multifox_file_size' => null,
                        'multifox_file_mime' => null,
                        'multifox_by' => null,
                        'multifox_at' => null,
                    ]
                    : ['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_REVISION, 'requires_multifox' => null, 'multifox_decided_by' => null, 'multifox_decided_at' => null]);
                $this->log($cuenta, CuentaCobroHistory::ACTION_CONTABILIDAD_DEVUELTA, $user, CuentaCobroWorkflow::STEP_APROBACION_CONTABLE,
                    "Devuelta por Contabilidad a Multifox. {$comment}");
            }

            return $cuenta;
        });

        if ($decision === 'aprobar') {
            $this->notifications->inPayments($cuenta, $comment);
        } elseif ($cuenta->current_step === CuentaCobroWorkflow::STEP_LIQUIDACION) {
            $erp = $this->accounting->usersWith([CuentaCobroWorkflow::PERMISSION_ERP], $cuenta->project_id)->push($liquidacionEmail)->push($cuenta->creator?->email);
            $this->accounting->returned('cuentas_cobro', $cuenta->project_id, $erp, [...$this->accountingPayload($cuenta), 'returned_to' => 'Liquidación ERP', 'tabs' => ['pending' => 'accounting_approval', 'returned' => 'erp']], (string) $comment, $user->name);
        } else {
            // Paula: permiso cortes.multifox (o el correo de Multifox de cuentas de cobro) y quien la había gestionado
            $multifox = $this->accounting->usersWith(['cortes.multifox', 'correos.cuentas_cobro.multifox'], $cuenta->project_id)->push($multifoxEmail);
            $this->accounting->returned('cuentas_cobro', $cuenta->project_id, $multifox, [...$this->accountingPayload($cuenta), 'returned_to' => 'Multifox'], (string) $comment, $user->name);
        }

        return $cuenta;
    }

    protected function accountingPayload(CuentaCobro $cuenta): array
    {
        $cuenta->loadMissing(['tipo:id,name', 'project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code', 'creator:id,name']);

        return [
            'module' => 'Cuentas de cobro',
            'reference' => "Cuenta de cobro {$cuenta->reference()}",
            'details' => [
                'Cuenta de cobro' => $cuenta->code,
                'Tipo' => $cuenta->tipo?->name,
                'N.º documento' => $cuenta->document_number,
                'N.º Multifox' => $cuenta->isDirecta() ? null : ($cuenta->multifox_number ?? ($cuenta->requires_multifox === false ? 'No requiere' : null)),
                ...($cuenta->isDirecta() ? collect(CuentaCobroWorkflow::STEPS)->mapWithKeys(fn (array $step, string $key) => [
                    $step['label'] => $cuenta->documents()->where('step', $key)->latest('id')->value('number'),
                ])->all() : []),
                'Valor' => '$' . number_format((float) $cuenta->amount, 0, ',', '.'),
                'Proveedor' => $cuenta->contractor?->name,
                'Proyecto / Torre' => trim(($cuenta->project?->name ?? '—') . ($cuenta->tower ? ' · ' . $cuenta->tower->name : '')),
                'Radicada por' => $cuenta->creator?->name,
            ],
            'cta_url' => config('cuentas_cobro.frontend_url') . '/app/contratacion/cuentas-cobro?cuenta=' . $cuenta->id,
            // Pestaña que abre el botón del correo según a quién le llega
            'tabs' => ['pending' => 'accounting_approval', 'returned' => 'multifox'],
        ];
    }

    public function downloadUrl(CuentaCobro $cuenta, string $file, ?int $adjuntoId = null): string
    {
        if ($file === 'adjunto') {
            $adjunto = $cuenta->adjuntos()->findOrFail($adjuntoId);

            return $this->storage->downloadUrl($adjunto->file_path, $adjunto->file_name);
        }
        if ($file === 'documento') {
            $document = $cuenta->documents()->findOrFail($adjuntoId);

            return $this->storage->downloadUrl($document->file_path, $document->file_name);
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

        $done = in_array($cuenta->status, [CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE, CuentaCobroWorkflow::STATUS_EN_PAGOS], true);
        if ($cuenta->isDirecta()) {
            return [...$steps, ...$this->directSteps($cuenta, $done), $this->accountingStep($cuenta, $history)];
        }
        $final = $history->whereIn('action', [CuentaCobroHistory::ACTION_SIN_MULTIFOX, CuentaCobroHistory::ACTION_MULTIFOX])->last();
        $steps[] = [
            'key' => CuentaCobroWorkflow::STEP_MULTIFOX,
            'label' => $cuenta->requires_multifox === false ? 'Sin Multifox' : 'Multifox',
            'kind' => 'document',
            'state' => match (true) {
                $done => 'aprobado',
                in_array($cuenta->status, CuentaCobroWorkflow::MULTIFOX_STATUSES, true) => 'actual',
                default => 'pendiente',
            },
            'user' => $done ? $final?->user?->name : null,
            'at' => $done ? $final?->created_at?->toIso8601String() : null,
        ];

        $steps[] = $this->accountingStep($cuenta, $history);

        return $steps;
    }

    /**
     * Pasos documentales de la directa (cargue ERP, factura, liquidación) para la línea de tiempo.
     */
    protected function directSteps(CuentaCobro $cuenta, bool $done): array
    {
        $documents = $cuenta->exists ? $cuenta->documents()->with('uploader:id,name')->get() : collect();
        $keys = array_keys(CuentaCobroWorkflow::STEPS);
        $currentIndex = $cuenta->current_step ? array_search($cuenta->current_step, $keys, true) : ($done ? count($keys) : -1);

        return array_map(function (string $step, int $index) use ($documents, $currentIndex) {
            $document = $documents->where('step', $step)->last();

            return [
                'key' => $step,
                'label' => CuentaCobroWorkflow::STEPS[$step]['label'],
                'kind' => 'document',
                'state' => match (true) {
                    $index === $currentIndex => 'actual',
                    $document !== null || $index < $currentIndex => 'aprobado',
                    default => 'pendiente',
                },
                'user' => $document?->uploader?->name,
                'at' => $document?->created_at?->toIso8601String(),
            ];
        }, $keys, array_keys($keys));
    }

    protected function accountingStep(CuentaCobro $cuenta, $history): array
    {
        $accounting = $history->whereIn('action', [CuentaCobroHistory::ACTION_CONTABILIDAD_APROBADA, CuentaCobroHistory::ACTION_CONTABILIDAD_DEVUELTA])->last();

        return [
            'key' => CuentaCobroWorkflow::STEP_APROBACION_CONTABLE,
            'label' => 'Aprobación Contabilidad',
            'kind' => 'approval',
            'state' => match (true) {
                $cuenta->status === CuentaCobroWorkflow::STATUS_EN_PAGOS => 'aprobado',
                $cuenta->status === CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE => 'actual',
                (in_array($cuenta->status, CuentaCobroWorkflow::MULTIFOX_STATUSES, true) || $cuenta->current_step === CuentaCobroWorkflow::STEP_LIQUIDACION)
                    && $accounting?->action === CuentaCobroHistory::ACTION_CONTABILIDAD_DEVUELTA => 'devuelto',
                default => 'pendiente',
            },
            'user' => $accounting?->user?->name,
            'at' => $accounting?->created_at?->toIso8601String(),
        ];
    }

    /**
     * Carga el documento del paso actual de la directa (cargue ERP o liquidación) y avanza.
     */
    public function uploadDocument(CuentaCobro $cuenta, array $data, User $user): CuentaCobroDocument
    {
        if (!$this->canUploadStep($cuenta, $user)) {
            throw new InvalidArgumentException('Esta cuenta de cobro no tiene un documento pendiente que puedas cargar.');
        }

        $step = $cuenta->current_step;
        $file = $this->storage->claimStepFile($data['upload_token'], $user, $cuenta, $step);
        $document = DB::transaction(fn () => $this->recordStepDocument($cuenta, $step, $data, $file, $user));
        $this->notifyStepDocument($document);

        return $document;
    }

    /**
     * Registra el documento de $step con el archivo ya guardado y avanza la cuenta. Debe llamarse dentro de una
     * transacción; el aviso lo envía notifyStepDocument() después del commit. Lo usan el cargue normal y las
     * facturas del mes (el residente asocia a su cuenta la factura que cargó Contabilidad).
     *
     * @param array{file_name: string, file_path: string, file_size: int, file_mime: ?string} $file
     */
    public function recordStepDocument(CuentaCobro $cuenta, string $step, array $data, array $file, User $user): CuentaCobroDocument
    {
        $observation = isset($data['observation']) ? (trim($data['observation']) ?: null) : null;

        $cuenta = CuentaCobro::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();
        if ($cuenta->current_step !== $step) {
            throw new InvalidArgumentException('Otro usuario ya cargó este documento.');
        }

        $document = $cuenta->documents()->create([
            'step' => $step,
            'number' => trim($data['number']),
            'amount' => $step === CuentaCobroWorkflow::STEP_FACTURA ? ($data['amount'] ?? null) : null,
            'observation' => $observation,
            'uploaded_by' => $user->id,
            ...$file,
        ]);

        // Con la liquidación pasa a la aprobación de Contabilidad antes del cuadro de pagos
        $nextStep = CuentaCobroWorkflow::nextStep($step);
        $cuenta->update($nextStep
            ? ['status' => CuentaCobroWorkflow::STEPS[$nextStep]['status'], 'current_step' => $nextStep]
            : ['status' => CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE, 'current_step' => null]);

        $this->log($cuenta, CuentaCobroHistory::ACTION_DOCUMENTO, $user, $step,
            CuentaCobroWorkflow::STEPS[$step]['number_label'] . " {$document->number}" . ($observation ? ". {$observation}" : ''), $document->file_name);

        return $document;
    }

    /**
     * Aviso a quien sigue: el residente (factura o liquidación), quien carga en el ERP, o Contabilidad.
     */
    public function notifyStepDocument(CuentaCobroDocument $document): void
    {
        $cuenta = $document->cuenta()->firstOrFail();

        $cuenta->status === CuentaCobroWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE
            ? $this->accounting->pending('cuentas_cobro', $cuenta->project_id, [
                ...$this->accountingPayload($cuenta),
                'pending_intro' => "La cuenta de cobro {$cuenta->reference()} ya tiene su liquidación ERP cargada.",
                'tabs' => ['pending' => 'accounting_approval', 'returned' => 'erp'],
            ], $document->observation)
            : $this->notifications->stepPending($cuenta, $document->observation);
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
            // Directa (con frente): Cargue ERP -> Factura -> Liquidación ERP en lugar de Multifox
            'is_directa' => $cuenta->isDirecta(),
            'current_step' => $cuenta->current_step,
            'current_step_label' => $cuenta->current_step ? CuentaCobroWorkflow::STEPS[$cuenta->current_step]['label'] : null,
            'current_step_number_label' => $cuenta->current_step ? CuentaCobroWorkflow::STEPS[$cuenta->current_step]['number_label'] : null,
            'can_upload_step' => $this->canUploadStep($cuenta, $user),
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
            'can_review_accounting' => $this->canReviewAccounting($cuenta, $user),
            'can_delete' => $user->can('cuentas_cobro.eliminar'),
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
            $data['documents'] = $cuenta->documents->map(fn (CuentaCobroDocument $d) => [
                'id' => $d->id,
                'step' => $d->step,
                'step_label' => $d->stepLabel(),
                'number' => $d->number,
                'amount' => $d->amount !== null ? (float) $d->amount : null,
                'observation' => $d->observation,
                'file_name' => $d->file_name,
                'file_size' => $d->file_size,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at?->toIso8601String(),
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
