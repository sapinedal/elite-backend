<?php

namespace App\Http\Modules\Facturacion\Service;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Service\AnticipoService;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteInvoiceService;
use App\Http\Modules\Cortes\Service\CorteStorageService;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Models\FacturacionInvoiceSupport;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Service\OrdenService;
use App\Http\Modules\Ordenes\Service\OrdenStorageService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Facturas del mes:
 *
 *   Contabilidad carga la factura (carpeta del mes) -> Pendiente de obra
 *   El residente la asocia a su corte aprobado o a su orden pendiente de factura y adjunta los soportes -> Procesada por obra
 *
 * Al asociarla se registra en el corte (pasa a Facturado y se avisa para Multifox) o en la orden (avanza a
 * Entrada de almacén). Contabilidad ve en el panel qué residente tiene cortes u órdenes esperando factura.
 *
 * La cotización no va a un corte ni a una orden: el residente la radica como anticipo de un contrato (queda
 * pendiente del Director de Obra en el flujo de anticipos).
 *
 * El indirecto no pasa por obra: al cargarlo queda procesado y pasa de una vez a cuentas por pagar.
 */
class FacturacionService
{
    public const PERMISSION_MANAGE = 'cortes.facturar'; // Contabilidad: carga facturas y ve el panel

    // Pestañas del listado
    public const SCOPE_PENDING = 'pending';
    public const SCOPE_PROCESSED = 'processed';
    public const SCOPE_ALL = 'all';

    public function __construct(
        protected FacturacionStorageService $storage,
        protected CorteInvoiceService $corteInvoices,
        protected OrdenService $ordenes,
        protected CorteStorageService $corteStorage,
        protected OrdenStorageService $ordenStorage,
        protected AnticipoService $anticipos,
        protected PagoService $pagos,
    ) {
    }

    public function canManage(User $user): bool
    {
        return $user->can(self::PERMISSION_MANAGE);
    }

    /**
     * Residentes: quienes radican cortes, órdenes o anticipos asocian las facturas a los suyos.
     */
    public function canAssociate(User $user): bool
    {
        return $user->can('cortes.crear') || $user->can('ordenes.crear') || $user->can('anticipos.crear');
    }

    /**
     * Contabilidad ve todo; el residente ve las pendientes (para encontrar las suyas) y las que él procesó.
     */
    public function canView(FacturacionInvoice $invoice, User $user): bool
    {
        return $this->canManage($user)
            || ($this->canAssociate($user) && ($invoice->status === FacturacionInvoice::STATUS_PENDIENTE || $invoice->processed_by === $user->id));
    }

    public function catalogs(User $user): array
    {
        $current = now()->startOfMonth();
        $periods = FacturacionInvoice::query()->distinct()->orderByDesc('period')->pluck('period')
            ->map(fn ($period) => Carbon::parse($period)->startOfMonth())
            ->push($current)
            ->unique(fn (Carbon $period) => $period->format('Y-m'))
            ->sortByDesc(fn (Carbon $period) => $period->format('Y-m'))
            ->values();

        return [
            'periods' => $periods->map(fn (Carbon $period) => [
                'value' => $period->format('Y-m'),
                'label' => FacturacionStorageService::monthLabel($period),
            ]),
            'current_period' => $current->format('Y-m'),
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'document_types' => collect(FacturacionInvoice::DOCUMENT_TYPES)
                ->map(fn (array $type, string $key) => ['key' => $key, 'label' => $type['label'], 'prefix' => $type['prefix']])
                ->values(),
            'statuses' => collect(FacturacionInvoice::STATUS_LABELS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'max_upload_mb' => config('facturacion.max_upload_mb'),
            's3_root' => trim(config('facturacion.s3_root'), '/'),
            'can_manage' => $this->canManage($user),
            'can_associate' => $this->canAssociate($user),
        ];
    }

    /**
     * Listado paginado. El residente que no es de Contabilidad solo ve pendientes y lo que él procesó.
     */
    public function list(User $user, string $scope, array $filters = []): array
    {
        $query = $this->scopedQuery($user, $scope)
            ->with(['contractor:id,name,nit', 'uploader:id,name', 'processor:id,name', 'corte:id,code,contract_id', 'corte.contract:id,contract_code', 'orden:id,code', 'anticipo:id,code,contract_id', 'anticipo.contract:id,contract_code', 'pagoCuenta:id,status', 'supports']);

        if (!empty($filters['period'])) {
            $query->whereDate('period', $this->parsePeriod($filters['period']));
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', $search)
                  ->orWhere('supplier_name', 'ilike', $search)
                  ->orWhere('file_name', 'ilike', $search)
                  ->orWhereHas('contractor', fn ($sub) => $sub->where('name', 'ilike', $search)->orWhere('nit', 'ilike', $search));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $suggested = $this->suggestedContractorIds($user);

        $page = $query->orderBy('status')->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (FacturacionInvoice $invoice) => $this->present($invoice, $user, $suggested));

        return [
            'page' => $page,
            'counts' => [
                self::SCOPE_PENDING => $this->scopedQuery($user, self::SCOPE_PENDING)->count(),
                // Pendientes del residente: las de proveedores con cortes suyos esperando factura
                'suggested' => $suggested->isEmpty() ? 0 : $this->scopedQuery($user, self::SCOPE_PENDING)->whereIn('contractor_id', $suggested)->count(),
            ],
        ];
    }

    protected function scopedQuery(User $user, string $scope): Builder
    {
        $query = FacturacionInvoice::query();

        if ($scope === self::SCOPE_PENDING) {
            return $query->where('status', FacturacionInvoice::STATUS_PENDIENTE);
        }
        if ($scope === self::SCOPE_PROCESSED) {
            $query->where('status', FacturacionInvoice::STATUS_PROCESADA);
        }

        return $this->canManage($user)
            ? $query
            : $query->where(fn ($q) => $q->where('status', FacturacionInvoice::STATUS_PENDIENTE)->orWhere('processed_by', $user->id));
    }

    public function get(FacturacionInvoice $invoice, User $user): array
    {
        $invoice->load(['contractor:id,name,nit', 'uploader:id,name', 'processor:id,name', 'corte:id,code,contract_id', 'corte.contract:id,contract_code', 'orden:id,code', 'anticipo:id,code,contract_id', 'anticipo.contract:id,contract_code', 'pagoCuenta:id,status', 'supports.uploader:id,name']);

        return $this->present($invoice, $user, $this->suggestedContractorIds($user));
    }

    /**
     * Contabilidad carga una factura ya subida a S3: queda en la carpeta del mes, pendiente de obra.
     * El indirecto no espera a obra: queda procesado y pasa de una vez a cuentas por pagar.
     */
    public function create(array $data, User $user): FacturacionInvoice
    {
        $period = $this->parsePeriod($data['period']);
        $contractor = !empty($data['contractor_id']) ? FtraContractor::find($data['contractor_id']) : null;
        $affectsAnother = FacturacionInvoice::requiresRelatedDocument($data['document_type']);

        $file = $this->storage->claimInvoiceFile($data['upload_token'], $user, $period);

        $fields = [
            'period' => $period,
            'contractor_id' => $contractor?->id,
            'supplier_name' => trim($data['supplier_name'] ?? '') ?: ($contractor?->name ?? 'Sin proveedor'),
            'document_type' => $data['document_type'],
            'invoice_number' => trim($data['invoice_number']),
            'related_document' => $affectsAnother ? trim($data['related_document']) : null,
            'amount' => $data['amount'] ?? null,
            'observation' => isset($data['observation']) ? (trim($data['observation']) ?: null) : null,
            'uploaded_by' => $user->id,
            'status' => FacturacionInvoice::STATUS_PENDIENTE,
            ...$file,
        ];

        if (!FacturacionInvoice::isIndirecto($data['document_type'])) {
            return FacturacionInvoice::create($fields);
        }

        try {
            return DB::transaction(function () use ($fields, $user) {
                $invoice = FacturacionInvoice::create($fields);
                $cuenta = $this->pagos->registerFromIndirecto($invoice, $user);
                $invoice->update([
                    'status' => FacturacionInvoice::STATUS_PROCESADA,
                    'target_type' => FacturacionInvoice::TARGET_PAGOS,
                    'pago_cuenta_id' => $cuenta->id,
                    'processed_by' => $user->id,
                    'processed_at' => now(),
                ]);

                return $invoice;
            });
        } catch (\Throwable $e) {
            // Sin registro no debe quedar el archivo en la carpeta del mes
            $this->storage->delete($file['file_path']);
            throw $e;
        }
    }

    /**
     * Contabilidad corrige los datos de una factura mientras está pendiente. El archivo y el mes no cambian
     * (el archivo ya está en la carpeta del mes); si el mes está mal, se elimina y se vuelve a cargar.
     */
    public function update(FacturacionInvoice $invoice, array $data, User $user): FacturacionInvoice
    {
        $this->assertPendingForManager($invoice, $user);
        // Cambiar a indirecto la mandaría a cuentas por pagar sin pasar por el cargue: se elimina y se vuelve a cargar
        if (FacturacionInvoice::isIndirecto($data['document_type'])) {
            throw new InvalidArgumentException('Para registrar un indirecto, elimina esta factura y cárgala de nuevo como Indirecto.');
        }
        $contractor = !empty($data['contractor_id']) ? FtraContractor::find($data['contractor_id']) : null;
        $affectsAnother = FacturacionInvoice::requiresRelatedDocument($data['document_type']);

        $invoice->update([
            'contractor_id' => $contractor?->id,
            'supplier_name' => trim($data['supplier_name'] ?? '') ?: ($contractor?->name ?? $invoice->supplier_name),
            'document_type' => $data['document_type'],
            'invoice_number' => trim($data['invoice_number']),
            'related_document' => $affectsAnother ? trim($data['related_document']) : null,
            'amount' => $data['amount'] ?? null,
            'observation' => isset($data['observation']) ? (trim($data['observation']) ?: null) : null,
        ]);

        return $invoice;
    }

    /**
     * Contabilidad elimina una factura cargada por error (solo si obra aún no la procesó), con su archivo.
     */
    public function delete(FacturacionInvoice $invoice, User $user): void
    {
        $this->assertPendingForManager($invoice, $user);

        DB::transaction(function () use ($invoice) {
            $invoice = FacturacionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
                throw new InvalidArgumentException('Obra ya procesó esta factura; no se puede eliminar.');
            }
            $invoice->delete();
        });

        $this->storage->delete($invoice->file_path);
    }

    protected function assertPendingForManager(FacturacionInvoice $invoice, User $user): void
    {
        if (!$this->canManage($user)) {
            throw new InvalidArgumentException('Solo Contabilidad puede modificar las facturas cargadas.');
        }
        if ($invoice->target_type === FacturacionInvoice::TARGET_PAGOS) {
            throw new InvalidArgumentException('Este indirecto ya está en cuentas por pagar; anúlalo desde allí si se cargó por error.');
        }
        if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
            throw new InvalidArgumentException('Obra ya procesó esta factura; no se puede modificar.');
        }
    }

    /**
     * Cortes del residente que admiten la factura: con las tres aprobaciones y aún sin factura (Aprobado).
     * Las notas crédito y facturas de reemplazo afectan una factura anterior, así que van a cortes ya Facturados.
     */
    public static function corteStatusesFor(?string $documentType): array
    {
        return FacturacionInvoice::requiresRelatedDocument($documentType)
            ? [CorteWorkflow::STATUS_FACTURADO]
            : [CorteWorkflow::STATUS_APROBADO];
    }

    /**
     * A qué puede asociar el residente: sus cortes con las tres aprobaciones y sus órdenes pendientes de factura;
     * la cotización, a los contratos de sus proyectos (se radica como anticipo).
     */
    public function targets(User $user, ?FacturacionInvoice $invoice = null): array
    {
        $isAnticipo = $invoice && FacturacionInvoice::isAnticipo($invoice->document_type);

        $cortes = Corte::query()
            ->where('created_by', $user->id)
            ->when($isAnticipo, fn ($q) => $q->whereRaw('1 = 0'))
            ->whereIn('status', self::corteStatusesFor($invoice?->document_type))
            ->with(['project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code'])
            ->withCount('invoices')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CorteWorkflow::STATUS_APROBADO])
            ->orderByDesc('approved_at')
            ->get();

        // Órdenes esperando la factura del proveedor (sin factura cargada). A una orden solo se asocia la factura,
        // cuenta de cobro o reembolso del proveedor, nunca notas crédito, facturas de reemplazo ni cotizaciones.
        $ordenCompatible = !$invoice || FacturacionInvoice::fitsOrden($invoice->document_type);
        $ordenes = Orden::query()
            ->where('created_by', $user->id)
            ->where('status', OrdenWorkflow::STATUS_PENDIENTE_FACTURA)
            ->where('current_step', OrdenWorkflow::STEP_FACTURA)
            ->whereDoesntHave('documents', fn ($q) => $q->where('step', OrdenWorkflow::STEP_FACTURA))
            ->when(!$ordenCompatible, fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['project:id,name', 'tower:id,name'])
            ->orderByDesc('approved_at')
            ->get();

        // Contratos de los proyectos del residente para radicar la cotización como anticipo: los del proveedor de la
        // cotización primero. Los que tienen otro proveedor asignado no se ofrecen.
        $contractorId = $invoice?->contractor_id;
        $contracts = !$isAnticipo || !$user->can('anticipos.crear') ? collect() : ContratacionContract::query()
            ->accessibleBy($user)
            ->when($contractorId, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('contractor_id')->orWhere('contractor_id', $contractorId)))
            ->with(['project:id,name', 'tower:id,name'])
            ->when($contractorId, fn ($q) => $q->orderByRaw('CASE WHEN contractor_id = ? THEN 0 ELSE 1 END', [$contractorId]))
            ->orderBy('contract_code')
            ->get(['id', 'project_id', 'tower_id', 'contractor_id', 'contract_code', 'contractor_name', 'object']);

        return [
            'cortes' => $cortes->map(fn (Corte $corte) => [
                'id' => $corte->id,
                'code' => $corte->code,
                'label' => $corte->label(),
                'status' => $corte->status,
                'status_label' => CorteWorkflow::statusLabel($corte->status),
                'project' => $corte->project?->name,
                'tower' => $corte->tower?->name,
                'contractor_id' => $corte->contractor_id,
                'contractor' => $corte->contractor?->name,
                'contract_code' => $corte->contract?->contract_code,
                'invoices_count' => $corte->invoices_count,
                'approved_at' => $corte->approved_at?->toIso8601String(),
            ])->values(),
            'ordenes' => $ordenes->map(fn (Orden $orden) => [
                'id' => $orden->id,
                'code' => $orden->code,
                'description' => $orden->description,
                'project' => $orden->project?->name,
                'tower' => $orden->tower?->name,
                'approved_at' => $orden->approved_at?->toIso8601String(),
            ])->values(),
            'contratos' => $contracts->map(fn (ContratacionContract $contract) => [
                'id' => $contract->id,
                'contract_code' => $contract->contract_code,
                'contractor_id' => $contract->contractor_id,
                'contractor_name' => $contract->contractor_name,
                'object' => $contract->object,
                'project' => $contract->project?->name,
                'tower' => $contract->tower?->name,
            ])->values(),
        ];
    }

    /**
     * El residente asocia la factura a su corte o a su orden con los soportes: queda procesada por obra y sigue
     * el flujo del corte (Facturado, aviso de Multifox) o de la orden (pasa a Entrada de almacén).
     * La factura queda en la carpeta del mes y se copia a la carpeta del corte (o de la orden), que es la que
     * queda registrada en el corte.
     *
     * La cotización se radica como anticipo del contrato elegido (target_id es el contrato): se copia a la carpeta
     * del anticipo y queda pendiente del Director de Obra.
     */
    public function associate(FacturacionInvoice $invoice, array $data, User $user): FacturacionInvoice
    {
        if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
            throw new InvalidArgumentException('Esta factura ya fue procesada por obra.');
        }

        // El valor no se pide al asociar: si Contabilidad no lo registró al cargar la factura, lo indica al aprobarla
        // (después de Multifox) en el corte o la orden. El anticipo sí necesita el valor al radicarse.
        $amount = $invoice->amount ?? ($data['amount'] ?? null);

        // Igual que en el corte, no se pide el proveedor al asociar: cuentas por pagar usa el de la factura del mes
        $contractorId = $invoice->contractor_id ?: ($data['contractor_id'] ?? null);

        $targetType = $data['target_type'];
        $target = match ($targetType) {
            FacturacionInvoice::TARGET_CORTE => Corte::find($data['target_id']),
            FacturacionInvoice::TARGET_ORDEN => Orden::find($data['target_id']),
            FacturacionInvoice::TARGET_ANTICIPO => ContratacionContract::query()->accessibleBy($user)->find($data['target_id']),
            default => null,
        };
        $this->assertCanAssociate($invoice, $target, $targetType, $amount, $contractorId, $user);

        // Los soportes se mueven a la carpeta del mes antes de la transacción (igual que los demás cargues)
        $supports = collect($data['support_tokens'] ?? [])
            ->map(fn (string $token) => $this->storage->claimSupportFile($token, $user, $invoice));

        // La copia del anticipo se hace dentro de la transacción (su carpeta lleva el código del anticipo)
        $copyPath = match ($targetType) {
            FacturacionInvoice::TARGET_CORTE => $this->corteStorage->copyInvoiceFile($target, $invoice->file_path),
            FacturacionInvoice::TARGET_ORDEN => $this->ordenStorage->copyStepFile($target, OrdenWorkflow::STEP_FACTURA, $invoice->file_path),
            default => null,
        };
        $file = $copyPath ? [...$invoice->only(['file_size', 'file_mime']), 'file_name' => basename($copyPath), 'file_path' => $copyPath] : [];
        $comment = isset($data['comment']) ? (trim($data['comment']) ?: null) : null;

        try {
            [$invoice, $record] = $this->associateRecords($invoice, $target, $targetType, $amount, $contractorId, $file, $comment, $supports, $user, $copyPath);
        } catch (\Throwable $e) {
            // Sin asociación no debe quedar la copia en la carpeta del corte / orden / anticipo
            if ($copyPath) {
                $this->storage->delete($copyPath);
            }
            throw $e;
        }

        // Después del commit, igual que en el cargue normal de cada módulo
        match (true) {
            $record instanceof CorteInvoice => $this->corteInvoices->notifyUploaded($record),
            $record instanceof Anticipo => $this->anticipos->notifySubmitted($record, $comment),
            default => $this->ordenes->notifyStepDocument($record),
        };

        return $invoice;
    }

    /**
     * Registra la factura en el corte o en la orden (con la copia $file), o radica el anticipo con la cotización,
     * y la marca como procesada por obra.
     *
     * @return array{0: FacturacionInvoice, 1: CorteInvoice|OrdenDocument|Anticipo}
     */
    protected function associateRecords(
        FacturacionInvoice $invoice, Corte|Orden|ContratacionContract $target, string $targetType, $amount, ?int $contractorId,
        array $file, ?string $comment, Collection $supports, User $user, ?string &$copyPath,
    ): array {
        return DB::transaction(function () use ($invoice, $target, $targetType, $amount, $contractorId, $file, $comment, $supports, $user, &$copyPath) {
            $invoice = FacturacionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
                throw new InvalidArgumentException('Otro usuario ya procesó esta factura.');
            }

            $record = match ($targetType) {
                FacturacionInvoice::TARGET_CORTE => $this->corteInvoices->register($target, [
                    'document_type' => FacturacionInvoice::DOCUMENT_TYPES[$invoice->document_type]['corte_type'],
                    'invoice_number' => $invoice->documentNumber(),
                    'related_document' => $invoice->related_document,
                    'amount' => $amount,
                    'observation' => $comment ?? $invoice->observation,
                ], $file, $user),
                FacturacionInvoice::TARGET_ORDEN => $this->ordenes->recordStepDocument($target, OrdenWorkflow::STEP_FACTURA, [
                    'number' => $invoice->documentNumber(),
                    'amount' => $amount,
                    'contractor_id' => $contractorId,
                    'observation' => $comment,
                ], $file, $user),
                FacturacionInvoice::TARGET_ANTICIPO => $this->anticipos->createFromFile([
                    'contract_id' => $target->id,
                    'contractor_id' => $contractorId,
                    'document_number' => $invoice->documentNumber(),
                    'amount' => $amount,
                    'description' => $invoice->observation,
                    'comment' => $comment,
                ], $invoice->only(['file_path', 'file_size', 'file_mime']), $user, $copyPath),
            };

            $invoice->update([
                'status' => FacturacionInvoice::STATUS_PROCESADA,
                'amount' => $amount,
                'contractor_id' => $contractorId,
                'target_type' => $targetType,
                'corte_id' => $targetType === FacturacionInvoice::TARGET_CORTE ? $target->id : null,
                'orden_id' => $targetType === FacturacionInvoice::TARGET_ORDEN ? $target->id : null,
                'corte_invoice_id' => $targetType === FacturacionInvoice::TARGET_CORTE ? $record->id : null,
                'orden_document_id' => $targetType === FacturacionInvoice::TARGET_ORDEN ? $record->id : null,
                'anticipo_id' => $targetType === FacturacionInvoice::TARGET_ANTICIPO ? $record->id : null,
                'processed_comment' => $comment,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);

            foreach ($supports as $support) {
                $invoice->supports()->create([...$support, 'uploaded_by' => $user->id]);
            }

            return [$invoice, $record];
        });
    }

    protected function assertCanAssociate(
        FacturacionInvoice $invoice, Corte|Orden|ContratacionContract|null $target, string $targetType, $amount, ?int $contractorId, User $user,
    ): void {
        $isAnticipo = FacturacionInvoice::isAnticipo($invoice->document_type);

        if (FacturacionInvoice::isIndirecto($invoice->document_type)) {
            throw new InvalidArgumentException('Los indirectos no se asocian a obra: pasan directo a cuentas por pagar.');
        }
        if ($isAnticipo !== ($targetType === FacturacionInvoice::TARGET_ANTICIPO)) {
            throw new InvalidArgumentException($isAnticipo
                ? 'La cotización se radica como anticipo de un contrato, no se asocia a cortes ni órdenes.'
                : 'Solo las cotizaciones se radican como anticipo.');
        }

        if ($targetType === FacturacionInvoice::TARGET_ANTICIPO) {
            if (!$user->can('anticipos.crear')) {
                throw new InvalidArgumentException('No tienes permiso para radicar anticipos.');
            }
            if (!$target) {
                throw new InvalidArgumentException('No tienes acceso al contrato seleccionado.');
            }
            if (!$contractorId) {
                throw new InvalidArgumentException('Indica el proveedor de la cotización.');
            }
            if ($target->contractor_id !== null && (int) $target->contractor_id !== (int) $contractorId) {
                throw new InvalidArgumentException('El contrato no pertenece al proveedor de la cotización.');
            }
            if ($amount === null) {
                throw new InvalidArgumentException('Indica el valor del anticipo.');
            }

            return;
        }

        $isCorte = $targetType === FacturacionInvoice::TARGET_CORTE;
        if (!$target || $target->created_by !== $user->id) {
            throw new InvalidArgumentException('Solo el residente responsable puede asociar facturas a su ' . ($isCorte ? 'corte.' : 'orden.'));
        }

        if ($isCorte && !in_array($target->status, CorteWorkflow::INVOICEABLE_STATUSES, true)) {
            throw new InvalidArgumentException("El {$target->label()} aún no tiene las tres aprobaciones.");
        }
        if ($isCorte && !in_array($target->status, self::corteStatusesFor($invoice->document_type), true)) {
            throw new InvalidArgumentException(FacturacionInvoice::requiresRelatedDocument($invoice->document_type)
                ? "El {$target->label()} aún no tiene una factura que esta {$invoice->documentTypeLabel()} pueda afectar."
                : "El {$target->label()} ya tiene factura asociada. Para corregirla usa una nota crédito o una factura de reemplazo.");
        }

        if (!$isCorte) {
            if ($target->current_step !== OrdenWorkflow::STEP_FACTURA) {
                throw new InvalidArgumentException("La orden {$target->code} no está esperando la factura del proveedor.");
            }
            if (!FacturacionInvoice::fitsOrden($invoice->document_type)) {
                throw new InvalidArgumentException('A una orden de compra solo se asocia la factura, cuenta de cobro o reembolso del proveedor.');
            }
        }
    }

    /**
     * Panel de Contabilidad: avance del mes, facturas pendientes por proveedor y, por residente, los cortes
     * aprobados y órdenes que siguen esperando factura (a quién le falta qué).
     */
    public function panel(User $user, ?string $period): array
    {
        $period = $this->parsePeriod($period ?: now()->format('Y-m'));
        $invoices = FacturacionInvoice::query()->whereDate('period', $period)->get();
        $pending = $invoices->where('status', FacturacionInvoice::STATUS_PENDIENTE);

        $cortes = Corte::query()
            ->accessibleBy($user)
            ->where('status', CorteWorkflow::STATUS_APROBADO)
            ->with(['creator:id,name,email', 'contractor:id,name', 'project:id,name', 'contract:id,contract_code'])
            ->orderBy('approved_at')
            ->get();

        $ordenes = Orden::query()
            ->accessibleBy($user)
            ->where('status', OrdenWorkflow::STATUS_PENDIENTE_FACTURA)
            ->with(['creator:id,name,email', 'project:id,name'])
            ->orderBy('approved_at')
            ->get();

        $processedBy = $invoices->where('status', FacturacionInvoice::STATUS_PROCESADA)->countBy('processed_by');

        $residentes = $cortes->pluck('creator')->merge($ordenes->pluck('creator'))->filter()->unique('id')
            ->map(function (User $residente) use ($cortes, $ordenes, $processedBy) {
                $own = fn (Collection $items) => $items->where('created_by', $residente->id)->values();

                return [
                    'id' => $residente->id,
                    'name' => $residente->name,
                    'email' => $residente->email,
                    'processed_in_period' => $processedBy->get($residente->id, 0),
                    'cortes' => $own($cortes)->map(fn (Corte $corte) => [
                        'id' => $corte->id,
                        'code' => $corte->code,
                        'label' => $corte->label(),
                        'contract_code' => $corte->contract?->contract_code,
                        'contractor' => $corte->contractor?->name,
                        'project' => $corte->project?->name,
                        'approved_at' => $corte->approved_at?->toIso8601String(),
                        'days_waiting' => $this->daysSince($corte->approved_at),
                    ]),
                    'ordenes' => $own($ordenes)->map(fn (Orden $orden) => [
                        'id' => $orden->id,
                        'code' => $orden->code,
                        'description' => $orden->description,
                        'project' => $orden->project?->name,
                        'approved_at' => $orden->approved_at?->toIso8601String(),
                        'days_waiting' => $this->daysSince($orden->approved_at),
                    ]),
                ];
            })
            ->sortByDesc(fn (array $r) => count($r['cortes']) + count($r['ordenes']))
            ->values();

        return [
            'period' => $period->format('Y-m'),
            'period_label' => FacturacionStorageService::monthLabel($period),
            'folder' => $this->storage->monthFolder($period),
            'summary' => [
                'total' => $invoices->count(),
                'processed' => $invoices->count() - $pending->count(),
                'pending' => $pending->count(),
                'total_amount' => (float) $invoices->sum('amount'),
                'pending_amount' => (float) $pending->sum('amount'),
                // Pendientes de meses anteriores que siguen sin procesar
                'pending_previous' => FacturacionInvoice::where('status', FacturacionInvoice::STATUS_PENDIENTE)->whereDate('period', '<', $period)->count(),
                'cortes_waiting' => $cortes->count(),
                'ordenes_waiting' => $ordenes->count(),
            ],
            'pending_by_supplier' => $pending->groupBy(fn (FacturacionInvoice $i) => $i->contractor_id ? "c{$i->contractor_id}" : mb_strtoupper($i->supplier_name))
                ->map(fn (Collection $items) => [
                    'supplier' => $items->first()->supplier_name,
                    'count' => $items->count(),
                    'amount' => (float) $items->sum('amount'),
                    'oldest_days' => $items->map(fn ($i) => $this->daysSince($i->created_at))->max(),
                    'documents' => $items->map(fn (FacturacionInvoice $i) => $i->documentNumber())->values(),
                ])
                ->sortByDesc('oldest_days')
                ->values(),
            'residentes' => $residentes,
        ];
    }

    public function downloadUrl(FacturacionInvoice $invoice): string
    {
        return $this->storage->downloadUrl($invoice->file_path, $invoice->file_name);
    }

    public function supportDownloadUrl(FacturacionInvoiceSupport $support): string
    {
        return $this->storage->downloadUrl($support->file_path, $support->file_name);
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->storage->requestUpload($fileName, $fileSize, $mime, $user);
    }

    /**
     * Proveedores de los cortes del residente que esperan factura: sus facturas pendientes se marcan como sugeridas.
     */
    protected function suggestedContractorIds(User $user): Collection
    {
        return Corte::query()
            ->where('created_by', $user->id)
            ->where('status', CorteWorkflow::STATUS_APROBADO)
            ->whereNotNull('contractor_id')
            ->distinct()
            ->pluck('contractor_id');
    }

    /**
     * "2026-10" -> primer día de ese mes.
     */
    protected function parsePeriod(string $value): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', substr($value, 0, 7) . '-01')->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('El mes no es válido.');
        }
    }

    protected function daysSince($date): ?int
    {
        return $date ? (int) Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    protected function present(FacturacionInvoice $invoice, User $user, Collection $suggested): array
    {
        $isPending = $invoice->status === FacturacionInvoice::STATUS_PENDIENTE;
        $target = match ($invoice->target_type) {
            FacturacionInvoice::TARGET_CORTE => ['type' => 'corte', 'id' => $invoice->corte_id, 'code' => $invoice->corte?->code, 'label' => $invoice->corte?->reference() ?? 'Corte eliminado'],
            FacturacionInvoice::TARGET_ORDEN => ['type' => 'orden', 'id' => $invoice->orden_id, 'code' => $invoice->orden?->code, 'label' => 'Orden ' . ($invoice->orden?->code ?? '—')],
            FacturacionInvoice::TARGET_PAGOS => ['type' => 'pagos', 'id' => $invoice->pago_cuenta_id, 'code' => null, 'label' => 'Cuentas por pagar · Indirectos'],
            FacturacionInvoice::TARGET_ANTICIPO => ['type' => 'anticipo', 'id' => $invoice->anticipo_id, 'code' => $invoice->anticipo?->code, 'label' => $invoice->anticipo?->reference() ?? 'Anticipo eliminado'],
            default => null,
        };

        return [
            'id' => $invoice->id,
            'period' => $invoice->period->format('Y-m'),
            'period_label' => FacturacionStorageService::monthLabel($invoice->period),
            'document_type' => $invoice->document_type,
            'document_type_label' => $invoice->documentTypeLabel(),
            'document_number' => $invoice->documentNumber(),
            'invoice_number' => $invoice->invoice_number,
            'related_document' => $invoice->related_document,
            'supplier_name' => $invoice->supplier_name,
            'contractor' => $invoice->contractor,
            'amount' => $invoice->amount !== null ? (float) $invoice->amount : null,
            'observation' => $invoice->observation,
            'file_name' => $invoice->file_name,
            'file_size' => $invoice->file_size,
            'uploaded_by' => $invoice->uploader?->name,
            'created_at' => $invoice->created_at?->toIso8601String(),
            'days_pending' => $isPending ? $this->daysSince($invoice->created_at) : null,
            'status' => $invoice->status,
            'status_label' => FacturacionInvoice::STATUS_LABELS[$invoice->status] ?? $invoice->status,
            'target' => $target,
            'processed_by' => $invoice->processor?->name,
            'processed_at' => $invoice->processed_at?->toIso8601String(),
            'processed_comment' => $invoice->processed_comment,
            'supports' => $invoice->supports->map(fn (FacturacionInvoiceSupport $s) => [
                'id' => $s->id,
                'file_name' => $s->file_name,
                'file_size' => $s->file_size,
            ])->values(),
            'suggested' => $isPending && $invoice->contractor_id && $suggested->contains($invoice->contractor_id),
            'can_edit' => $isPending && $this->canManage($user),
            'can_associate' => $isPending && $this->canAssociate($user),
        ];
    }
}
