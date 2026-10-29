<?php

namespace App\Http\Modules\Facturacion\Service;

use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteInvoiceService;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Models\FacturacionInvoiceSupport;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Service\OrdenService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
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
    ) {
    }

    public function canManage(User $user): bool
    {
        return $user->can(self::PERMISSION_MANAGE);
    }

    /**
     * Residentes: quienes radican cortes u órdenes asocian las facturas a los suyos.
     */
    public function canAssociate(User $user): bool
    {
        return $user->can('cortes.crear') || $user->can('ordenes.crear');
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
            ->with(['contractor:id,name,nit', 'uploader:id,name', 'processor:id,name', 'corte:id,code', 'orden:id,code', 'supports']);

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
        $invoice->load(['contractor:id,name,nit', 'uploader:id,name', 'processor:id,name', 'corte:id,code', 'orden:id,code', 'supports.uploader:id,name']);

        return $this->present($invoice, $user, $this->suggestedContractorIds($user));
    }

    /**
     * Contabilidad carga una factura ya subida a S3: queda en la carpeta del mes, pendiente de obra.
     */
    public function create(array $data, User $user): FacturacionInvoice
    {
        $period = $this->parsePeriod($data['period']);
        $contractor = !empty($data['contractor_id']) ? FtraContractor::find($data['contractor_id']) : null;
        $affectsAnother = FacturacionInvoice::requiresRelatedDocument($data['document_type']);

        $file = $this->storage->claimInvoiceFile($data['upload_token'], $user, $period);

        return FacturacionInvoice::create([
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
        ]);
    }

    /**
     * Contabilidad corrige los datos de una factura mientras está pendiente. El archivo y el mes no cambian
     * (el archivo ya está en la carpeta del mes); si el mes está mal, se elimina y se vuelve a cargar.
     */
    public function update(FacturacionInvoice $invoice, array $data, User $user): FacturacionInvoice
    {
        $this->assertPendingForManager($invoice, $user);
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
        if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
            throw new InvalidArgumentException('Obra ya procesó esta factura; no se puede modificar.');
        }
    }

    /**
     * A qué puede asociar el residente: sus cortes con las tres aprobaciones y sus órdenes pendientes de factura.
     */
    public function targets(User $user): array
    {
        $cortes = Corte::query()
            ->where('created_by', $user->id)
            ->whereIn('status', CorteWorkflow::INVOICEABLE_STATUSES)
            ->with(['project:id,name', 'tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code'])
            ->withCount('invoices')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CorteWorkflow::STATUS_APROBADO])
            ->orderByDesc('approved_at')
            ->get();

        $ordenes = Orden::query()
            ->where('created_by', $user->id)
            ->where('status', OrdenWorkflow::STATUS_PENDIENTE_FACTURA)
            ->with(['project:id,name', 'tower:id,name'])
            ->orderByDesc('approved_at')
            ->get();

        return [
            'cortes' => $cortes->map(fn (Corte $corte) => [
                'id' => $corte->id,
                'code' => $corte->code,
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
        ];
    }

    /**
     * El residente asocia la factura a su corte o a su orden con los soportes: queda procesada por obra y sigue
     * el flujo del corte (Facturado, aviso de Multifox) o de la orden (pasa a Entrada de almacén).
     */
    public function associate(FacturacionInvoice $invoice, array $data, User $user): FacturacionInvoice
    {
        if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
            throw new InvalidArgumentException('Esta factura ya fue procesada por obra.');
        }

        $amount = $invoice->amount ?? ($data['amount'] ?? null);
        if (!$amount) {
            throw new InvalidArgumentException('Indica el valor de la factura: Contabilidad no lo registró.');
        }

        $isCorte = $data['target_type'] === FacturacionInvoice::TARGET_CORTE;
        $target = $isCorte ? Corte::find($data['target_id']) : Orden::find($data['target_id']);
        $this->assertCanAssociate($invoice, $target, $isCorte, $user);

        $contractorId = $invoice->contractor_id ?: ($data['contractor_id'] ?? null);
        if (!$isCorte && !$contractorId) {
            throw new InvalidArgumentException('Indica el proveedor de la factura: con él pasa a cuentas por pagar.');
        }

        // Los soportes se mueven a la carpeta del mes antes de la transacción (igual que los demás cargues)
        $supports = collect($data['support_tokens'])
            ->map(fn (string $token) => $this->storage->claimSupportFile($token, $user, $invoice));

        $file = $invoice->only(['file_name', 'file_path', 'file_size', 'file_mime']);
        $comment = isset($data['comment']) ? (trim($data['comment']) ?: null) : null;

        [$invoice, $record] = DB::transaction(function () use ($invoice, $target, $isCorte, $amount, $contractorId, $file, $comment, $supports, $user) {
            $invoice = FacturacionInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== FacturacionInvoice::STATUS_PENDIENTE) {
                throw new InvalidArgumentException('Otro usuario ya procesó esta factura.');
            }

            $record = $isCorte
                ? $this->corteInvoices->register($target, [
                    'document_type' => FacturacionInvoice::DOCUMENT_TYPES[$invoice->document_type]['corte_type'],
                    'invoice_number' => $invoice->documentNumber(),
                    'related_document' => $invoice->related_document,
                    'amount' => $amount,
                    'observation' => $comment ?? $invoice->observation,
                ], $file, $user)
                : $this->ordenes->recordStepDocument($target, OrdenWorkflow::STEP_FACTURA, [
                    'number' => $invoice->documentNumber(),
                    'amount' => $amount,
                    'contractor_id' => $contractorId,
                    'observation' => $comment,
                ], $file, $user);

            $invoice->update([
                'status' => FacturacionInvoice::STATUS_PROCESADA,
                'amount' => $amount,
                'contractor_id' => $contractorId,
                'target_type' => $isCorte ? FacturacionInvoice::TARGET_CORTE : FacturacionInvoice::TARGET_ORDEN,
                'corte_id' => $isCorte ? $target->id : null,
                'orden_id' => $isCorte ? null : $target->id,
                'corte_invoice_id' => $isCorte ? $record->id : null,
                'orden_document_id' => $isCorte ? null : $record->id,
                'processed_comment' => $comment,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);

            foreach ($supports as $support) {
                $invoice->supports()->create([...$support, 'uploaded_by' => $user->id]);
            }

            return [$invoice, $record];
        });

        // Después del commit, igual que en el cargue normal de cada módulo
        $record instanceof CorteInvoice
            ? $this->corteInvoices->notifyUploaded($record)
            : $this->ordenes->notifyStepDocument($record);

        return $invoice;
    }

    protected function assertCanAssociate(FacturacionInvoice $invoice, Corte|Orden|null $target, bool $isCorte, User $user): void
    {
        if (!$target || $target->created_by !== $user->id) {
            throw new InvalidArgumentException('Solo el residente responsable puede asociar facturas a su ' . ($isCorte ? 'corte.' : 'orden.'));
        }

        if ($isCorte && !in_array($target->status, CorteWorkflow::INVOICEABLE_STATUSES, true)) {
            throw new InvalidArgumentException("El corte {$target->code} aún no tiene las tres aprobaciones.");
        }

        if (!$isCorte) {
            if ($target->current_step !== OrdenWorkflow::STEP_FACTURA) {
                throw new InvalidArgumentException("La orden {$target->code} no está esperando la factura del proveedor.");
            }
            if (!in_array($invoice->document_type, [FacturacionInvoice::TYPE_FACTURA, FacturacionInvoice::TYPE_CUENTA_COBRO], true)) {
                throw new InvalidArgumentException('A una orden de compra solo se asocia la factura o cuenta de cobro del proveedor.');
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
            ->with(['creator:id,name,email', 'contractor:id,name', 'project:id,name'])
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
            FacturacionInvoice::TARGET_CORTE => ['type' => 'corte', 'id' => $invoice->corte_id, 'code' => $invoice->corte?->code, 'label' => 'Corte ' . ($invoice->corte?->code ?? '—')],
            FacturacionInvoice::TARGET_ORDEN => ['type' => 'orden', 'id' => $invoice->orden_id, 'code' => $invoice->orden?->code, 'label' => 'Orden ' . ($invoice->orden?->code ?? '—')],
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
