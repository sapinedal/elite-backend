<?php

namespace App\Http\Modules\Pagos\Service;

use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteStorageService;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Models\OrdenDocument;
use App\Http\Modules\Ordenes\Service\OrdenStorageService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Pagos\Models\PagoMovimiento;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cuentas por pagar (antes el Excel semanal "Pagos autorizados"):
 *
 *   Pendiente --(Paula prioriza y programa)--> Priorizada --(Contabilidad paga)--> Pagada
 *                                                  \-- abono --> Parcial (vuelve a priorizarse el saldo)
 *
 * Las cuentas llegan solas, después de la aprobación de Contabilidad, desde cortes (factura confirmada en Multifox),
 * órdenes (entrada cargada), anticipos y cuentas de cobro,
 * o las registra Contabilidad a mano. Cada acción queda en pagos_movimientos con usuario y fecha.
 */
class PagoService
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_PRIORIZADA = 'priorizada';
    public const STATUS_PARCIAL = 'parcial';
    public const STATUS_PAGADA = 'pagada';
    public const STATUS_ANULADA = 'anulada';

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE => 'Por priorizar',
        self::STATUS_PRIORIZADA => 'Programada',
        self::STATUS_PARCIAL => 'Abono parcial',
        self::STATUS_PAGADA => 'Pagada',
        self::STATUS_ANULADA => 'Anulada',
    ];

    public const PRIORITIES = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];

    public const SCOPE_TO_PRIORITIZE = 'por_priorizar';
    public const SCOPE_SCHEDULED = 'programadas';
    public const SCOPE_PAID = 'pagadas';
    public const SCOPE_ALL = 'todas';

    protected const SCOPE_STATUSES = [
        self::SCOPE_TO_PRIORITIZE => [self::STATUS_PENDIENTE, self::STATUS_PARCIAL],
        self::SCOPE_SCHEDULED => [self::STATUS_PRIORIZADA],
        self::SCOPE_PAID => [self::STATUS_PAGADA],
    ];

    public const SOURCE_LABELS = [
        PagoCuenta::SOURCE_CORTE => 'Corte',
        PagoCuenta::SOURCE_ORDEN => 'Orden',
        PagoCuenta::SOURCE_ANTICIPO => 'Anticipo',
        PagoCuenta::SOURCE_CUENTA_COBRO => 'Cuenta de cobro',
        PagoCuenta::SOURCE_MANUAL => 'Manual',
        PagoCuenta::SOURCE_INDIRECTO => 'Indirecto',
    ];

    public function __construct(
        protected DirectUploadService $uploads,
        protected CorteStorageService $corteStorage,
        protected OrdenStorageService $ordenStorage,
    ) {
    }

    public function canPrioritize(User $user): bool
    {
        return $user->can('pagos.priorizar');
    }

    public function canPay(User $user): bool
    {
        return $user->can('pagos.pagar');
    }

    public function canRegister(User $user): bool
    {
        return $user->can('pagos.registrar');
    }

    public function canView(User $user): bool
    {
        return $user->can('pagos.ver') || $this->canPrioritize($user);
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

        // Frentes del cuadro: indirectos, las torres de cada proyecto y los adicionales configurados
        $frentes = collect([config('pagos.frente_indirectos')])
            ->merge($projects->flatMap(fn ($p) => $p->towers->pluck('name')))
            ->merge(config('pagos.frentes_extra', []))
            ->map(fn ($f) => mb_strtoupper(trim($f)))
            ->unique()
            ->values();

        return [
            'projects' => $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'frentes' => $frentes,
            'contractors' => FtraContractor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'nit']),
            'statuses' => collect(self::STATUS_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'priorities' => collect(self::PRIORITIES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'next_payment_date' => now()->next('Friday')->toDateString(),
            'max_upload_mb' => config('pagos.max_upload_mb'),
            'can_prioritize' => $this->canPrioritize($user),
            'can_pay' => $this->canPay($user),
            'can_register' => $this->canRegister($user),
        ];
    }

    protected function filteredQuery(User $user, string $scope, array $filters): Builder
    {
        $projectIds = $user->accessibleProjectIds();
        $query = PagoCuenta::query()
            // Las cuentas manuales sin proyecto las ve todo el que tenga acceso al módulo
            ->when($projectIds !== null, fn ($q) => $q->where(fn ($sub) => $sub->whereIn('project_id', $projectIds)->orWhereNull('project_id')));

        if (isset(self::SCOPE_STATUSES[$scope])) {
            $query->whereIn('status', self::SCOPE_STATUSES[$scope]);
        } elseif (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        foreach (['project_id', 'frente', 'priority', 'source_type'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (!empty($filters['scheduled_for'])) {
            $query->whereDate('scheduled_for', $filters['scheduled_for']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('supplier_name', 'ilike', $search)
                ->orWhere('supplier_nit', 'ilike', $search)
                ->orWhere('document_number', 'ilike', $search)
                ->orWhere('concept', 'ilike', $search));
        }

        return $query;
    }

    public function listCuentas(User $user, string $scope, array $filters = []): array
    {
        $query = $this->filteredQuery($user, $scope, $filters);
        $total = (float) (clone $query)->sum(DB::raw('amount - adjustments - paid_amount'));

        $query->with(['project:id,name', 'corte:id,code', 'orden:id,code', 'anticipo:id,code', 'cuentaCobro:id,code', 'prioritizer:id,name']);
        $query = match ($scope) {
            // Programadas: primero la fecha de pago y la prioridad
            self::SCOPE_SCHEDULED => $query->orderBy('scheduled_for')
                ->orderByRaw("case priority when 'alta' then 1 when 'media' then 2 else 3 end"),
            self::SCOPE_PAID => $query->orderByDesc('paid_at'),
            default => $query->orderBy('frente')->orderBy('supplier_name')->orderBy('id'),
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 200);
        $page = $query->paginate($perPage)->through(fn (PagoCuenta $c) => $this->present($c, $user));

        return ['page' => $page, 'total_balance' => round($total, 2)];
    }

    public function counts(User $user): array
    {
        return collect(self::SCOPE_STATUSES)
            ->map(fn ($statuses, $scope) => $this->filteredQuery($user, $scope, [])->count())
            ->all();
    }

    /**
     * Cuentas para exportar al formato del cuadro (secciones por frente), con los mismos filtros del listado.
     */
    public function exportRows(User $user, string $scope, array $filters): array
    {
        // Frentes en el orden del cuadro: indirectos, torres, urbanismo y los adicionales; los demás al final
        $order = collect($this->catalogs($user)['frentes'])->flip();

        return $this->filteredQuery($user, $scope, $filters)
            ->with(['corte:id,code', 'orden:id,code', 'anticipo:id,code', 'cuentaCobro:id,code'])
            ->orderBy('supplier_name')->orderBy('id')
            ->get()
            ->groupBy('frente')
            ->sortBy(fn ($rows, $frente) => $order[$frente] ?? PHP_INT_MAX)
            ->map(fn ($rows) => $rows->map(fn (PagoCuenta $c) => $this->present($c, $user))->values()->all())
            ->all();
    }

    public function getCuenta(PagoCuenta $cuenta, User $user): array
    {
        $cuenta->load(['project:id,name', 'corte:id,code', 'orden:id,code', 'anticipo:id,code', 'cuentaCobro:id,code', 'prioritizer:id,name', 'creator:id,name', 'movimientos.user:id,name']);

        return $this->present($cuenta, $user, true);
    }

    public function canAccess(PagoCuenta $cuenta, User $user): bool
    {
        return $this->canView($user) && ($cuenta->project_id === null || $user->canAccessProject($cuenta->project_id));
    }

    /**
     * Factura de corte confirmada en Multifox: crea la cuenta, reemplaza la anterior o aplica la nota crédito.
     */
    public function registerFromCorteInvoice(CorteInvoice $invoice, User $user): void
    {
        $invoice->loadMissing(['corte.tower:id,name', 'corte.contractor:id,name,nit', 'corte.contract:id,contract_code']);
        $corte = $invoice->corte;
        $amount = (float) $invoice->amount;

        $related = $invoice->related_document
            ? PagoCuenta::where('corte_id', $corte->id)
                ->where('document_number', $invoice->related_document)
                ->where('status', '!=', self::STATUS_ANULADA)
                ->first()
            : null;

        if ($invoice->document_type === CorteInvoice::TYPE_NOTA_CREDITO) {
            // La factura que anula nunca llegó a cuentas por pagar (quedó anulada): no hay nada que descontar
            $voided = CorteInvoice::where('corte_id', $corte->id)->where('accounting_status', CorteInvoice::ACCOUNTING_ANULADA)
                ->whereRaw('UPPER(TRIM(invoice_number)) = ?', [mb_strtoupper(trim((string) $invoice->related_document))])->exists();
            if (!$related && $voided) {
                return;
            }
            if (!$related) {
                throw new InvalidArgumentException("La factura {$invoice->related_document} que afecta esta nota crédito aún no está en cuentas por pagar. Confirma primero esa factura en Multifox.");
            }
            $related->adjustments = (float) $related->adjustments + $amount;
            $this->refreshStatus($related);
            $related->save();
            $this->log($related, PagoMovimiento::ACTION_NOTA_CREDITO, $user, $amount, "Nota crédito N.º {$invoice->invoice_number}. {$invoice->observation}");

            return;
        }

        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_CORTE,
            'source_id' => $invoice->id,
            'corte_id' => $corte->id,
            'project_id' => $corte->project_id,
            'frente' => $this->frenteFor($corte->tower?->name),
            'contractor_id' => $corte->contractor_id,
            'supplier_nit' => $corte->contractor?->nit,
            'supplier_name' => $corte->contractor?->name ?? 'Proveedor sin nombre',
            'document_number' => $invoice->invoice_number,
            'concept' => $corte->reference() . ($invoice->observation ? ". {$invoice->observation}" : ''),
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Factura confirmada en Multifox y aprobada por Contabilidad ({$corte->reference()}).");

        // Factura de reemplazo: la anterior se anula y lo que ya se le había pagado pasa a la nueva
        if ($invoice->document_type === CorteInvoice::TYPE_FACTURA_REEMPLAZO && $related) {
            $cuenta->update(['paid_amount' => $related->paid_amount, 'adjustments' => $related->adjustments]);
            $this->refreshStatus($cuenta);
            $cuenta->save();
            $related->update(['status' => self::STATUS_ANULADA, 'scheduled_for' => null, 'priority' => null]);
            $this->log($related, PagoMovimiento::ACTION_REEMPLAZO, $user, null, "Reemplazada por la factura {$invoice->invoice_number}.");
            $this->log($cuenta, PagoMovimiento::ACTION_REEMPLAZO, $user, (float) $related->paid_amount, "Reemplaza la factura {$related->document_number}; se trasladan sus pagos y ajustes.");
        }
    }

    /**
     * Orden completada (entrada cargada y aprobada por Contabilidad): la factura del proveedor pasa a cuentas por pagar.
     * El proveedor sale del documento o, si no se indicó, de la factura del mes que se asoció a la orden.
     */
    public function registerFromOrden(Orden $orden, User $user): void
    {
        $orden->loadMissing(['tower:id,name', 'documents']);
        $invoice = $orden->documents->firstWhere('step', OrdenWorkflow::STEP_FACTURA);
        if (!$invoice || PagoCuenta::where('source_type', PagoCuenta::SOURCE_ORDEN)->where('source_id', $orden->id)->exists()) {
            return;
        }

        $contractor = $invoice->contractor_id ? FtraContractor::find($invoice->contractor_id) : null;
        $monthly = $contractor ? null : FacturacionInvoice::with('contractor:id,name,nit')->where('orden_document_id', $invoice->id)->first();
        $contractor ??= $monthly?->contractor;
        $amount = (float) $invoice->amount;

        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_ORDEN,
            'source_id' => $orden->id,
            'orden_id' => $orden->id,
            'project_id' => $orden->project_id,
            'frente' => $this->frenteFor($orden->tower?->name),
            'contractor_id' => $contractor?->id,
            'supplier_nit' => $contractor?->nit,
            'supplier_name' => $contractor?->name ?? $monthly?->supplier_name ?? 'Proveedor sin nombre',
            'document_number' => $invoice->number,
            'concept' => "Orden {$orden->code}. " . $orden->description,
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Orden {$orden->code} completada (entrada de almacén cargada y aprobada por Contabilidad).");
    }

    /**
     * Orden de compra ERP enviada a cuentas por pagar con su cargue (se paga antes de la entrada de almacén, que se
     * legaliza después). Valor y proveedor salen de la orden ERP. Debe correr dentro de la transacción del cargue.
     */
    public function registerFromOrdenErp(Orden $orden, OrdenDocument $document, User $user): void
    {
        $orden->loadMissing('tower:id,name');
        if (PagoCuenta::where('source_type', PagoCuenta::SOURCE_ORDEN)->where('source_id', $orden->id)->where('status', '!=', self::STATUS_ANULADA)->exists()) {
            return;
        }

        $contractor = $document->contractor_id ? FtraContractor::find($document->contractor_id) : null;
        $amount = (float) $document->amount;

        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_ORDEN,
            'source_id' => $orden->id,
            'orden_id' => $orden->id,
            'project_id' => $orden->project_id,
            'frente' => $this->frenteFor($orden->tower?->name),
            'contractor_id' => $contractor?->id,
            'supplier_nit' => $contractor?->nit,
            'supplier_name' => $contractor?->name ?? 'Proveedor sin nombre',
            'document_number' => $document->number,
            'concept' => "Orden {$orden->code} (orden de compra ERP). " . $orden->description,
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Orden de compra ERP {$document->number} de la orden {$orden->code} enviada a cuentas por pagar con su cargue. La entrada de almacén se legaliza después.");
    }

    /**
     * Anticipo generado en Multifox por Contabilidad: la cuenta de cobro del anticipo pasa a cuentas por pagar.
     */
    public function registerFromAnticipo(Anticipo $anticipo, User $user): void
    {
        $anticipo->loadMissing(['tower:id,name', 'contractor:id,name,nit', 'contract:id,contract_code,contractor_name']);
        if (PagoCuenta::where('source_type', PagoCuenta::SOURCE_ANTICIPO)->where('source_id', $anticipo->id)->exists()) {
            return;
        }

        $amount = (float) $anticipo->amount;
        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_ANTICIPO,
            'source_id' => $anticipo->id,
            'anticipo_id' => $anticipo->id,
            'project_id' => $anticipo->project_id,
            'frente' => $this->frenteFor($anticipo->tower?->name),
            'contractor_id' => $anticipo->contractor_id,
            'supplier_nit' => $anticipo->contractor?->nit,
            'supplier_name' => $anticipo->contractor?->name ?? $anticipo->contract?->contractor_name ?? 'Proveedor sin nombre',
            'document_number' => $anticipo->document_number,
            'concept' => "Anticipo {$anticipo->reference()}" . ($anticipo->multifox_number ? ". Multifox N.º {$anticipo->multifox_number}" : ''),
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Anticipo {$anticipo->code} generado en Multifox.");
    }

    /**
     * Cuenta de cobro aprobada (con o sin cargue en Multifox, según decidió Paula): pasa a cuentas por pagar.
     */
    public function registerFromCuentaCobro(CuentaCobro $cuentaCobro, User $user): void
    {
        $cuentaCobro->loadMissing(['tipo:id,name', 'tower:id,name', 'contractor:id,name,nit']);
        if (PagoCuenta::where('source_type', PagoCuenta::SOURCE_CUENTA_COBRO)->where('source_id', $cuentaCobro->id)->exists()) {
            return;
        }

        $amount = (float) $cuentaCobro->amount;
        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_CUENTA_COBRO,
            'source_id' => $cuentaCobro->id,
            'cuenta_cobro_id' => $cuentaCobro->id,
            'project_id' => $cuentaCobro->project_id,
            'frente' => $this->frenteFor($cuentaCobro->tower?->name),
            'contractor_id' => $cuentaCobro->contractor_id,
            'supplier_nit' => $cuentaCobro->contractor?->nit,
            'supplier_name' => $cuentaCobro->contractor?->name ?? 'Proveedor sin nombre',
            'document_number' => $cuentaCobro->document_number,
            'concept' => "Cuenta de cobro {$cuentaCobro->reference()}" . ($cuentaCobro->description ? ". {$cuentaCobro->description}" : ''),
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Cuenta de cobro {$cuentaCobro->code} "
            . match (true) {
                $cuentaCobro->isDirecta() => 'liquidada en el ERP y aprobada por Contabilidad.',
                (bool) $cuentaCobro->requires_multifox => "cargada en Multifox (N.º {$cuentaCobro->multifox_number}).",
                default => 'aprobada (no requirió Multifox).',
            });
    }

    /**
     * Factura del mes de tipo indirecto: no pasa por obra, Contabilidad la carga y queda de una en cuentas por pagar,
     * en el frente de indirectos. Debe correr dentro de la transacción del cargue.
     */
    public function registerFromIndirecto(FacturacionInvoice $invoice, User $user): PagoCuenta
    {
        $invoice->loadMissing('contractor:id,name,nit');
        $amount = (float) $invoice->amount;

        $cuenta = PagoCuenta::create([
            'source_type' => PagoCuenta::SOURCE_INDIRECTO,
            'source_id' => $invoice->id,
            'project_id' => null,
            'frente' => $this->frenteFor(null),
            'contractor_id' => $invoice->contractor_id,
            'supplier_nit' => $invoice->contractor?->nit,
            'supplier_name' => $invoice->contractor?->name ?? $invoice->supplier_name,
            'document_number' => $invoice->documentNumber(),
            'concept' => 'Indirecto' . ($invoice->observation ? ". {$invoice->observation}" : ''),
            'amount' => $amount,
            'status' => self::STATUS_PENDIENTE,
            'created_by' => $user->id,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, $amount, "Factura indirecta {$invoice->documentNumber()} cargada por Contabilidad en las facturas del mes.");

        return $cuenta;
    }

    /**
     * Cuenta registrada a mano por Contabilidad (nómina, aportes, fiduciaria, cuentas de cobro...).
     */
    public function createManual(array $data, User $user): PagoCuenta
    {
        if (!$this->canRegister($user)) {
            throw new InvalidArgumentException('No tienes permiso para registrar cuentas por pagar.');
        }

        $contractor = !empty($data['contractor_id']) ? FtraContractor::find($data['contractor_id']) : null;

        return DB::transaction(function () use ($data, $user, $contractor) {
            $cuenta = PagoCuenta::create([
                'source_type' => PagoCuenta::SOURCE_MANUAL,
                'project_id' => $data['project_id'] ?? null,
                'frente' => mb_strtoupper(trim($data['frente'])),
                'contractor_id' => $contractor?->id,
                'supplier_nit' => $contractor?->nit ?? ($data['supplier_nit'] ?? null),
                'supplier_name' => $contractor?->name ?? trim($data['supplier_name']),
                'document_number' => trim($data['document_number']),
                'concept' => isset($data['concept']) ? trim($data['concept']) : null,
                'amount' => $data['amount'],
                'status' => self::STATUS_PENDIENTE,
                'created_by' => $user->id,
            ]);
            $this->log($cuenta, PagoMovimiento::ACTION_CREADA, $user, (float) $cuenta->amount, 'Registrada manualmente.');

            return $cuenta;
        });
    }

    /**
     * Paula prioriza y programa la fecha de pago de varias cuentas a la vez.
     *
     * @param list<int> $ids
     */
    public function prioritize(array $ids, string $priority, string $scheduledFor, ?string $comment, User $user): int
    {
        if (!$this->canPrioritize($user)) {
            throw new InvalidArgumentException('No tienes permiso para priorizar pagos.');
        }

        return DB::transaction(function () use ($ids, $priority, $scheduledFor, $comment, $user) {
            $cuentas = PagoCuenta::whereIn('id', $ids)
                ->whereIn('status', [self::STATUS_PENDIENTE, self::STATUS_PARCIAL, self::STATUS_PRIORIZADA])
                ->lockForUpdate()
                ->get()
                ->filter(fn (PagoCuenta $c) => $this->canAccess($c, $user));

            foreach ($cuentas as $cuenta) {
                $cuenta->update([
                    'status' => self::STATUS_PRIORIZADA,
                    'priority' => $priority,
                    'scheduled_for' => $scheduledFor,
                    'prioritized_by' => $user->id,
                    'prioritized_at' => now(),
                ]);
                $date = $cuenta->scheduled_for->format('d/m/Y');
                $this->log($cuenta, PagoMovimiento::ACTION_PRIORIZADA, $user, $cuenta->balance(),
                    "Prioridad " . self::PRIORITIES[$priority] . ", pago programado para el {$date}." . ($comment ? " {$comment}" : ''));
            }

            return $cuentas->count();
        });
    }

    /**
     * Saca una cuenta de la programación (vuelve a "Por priorizar").
     */
    public function unschedule(PagoCuenta $cuenta, ?string $comment, User $user): PagoCuenta
    {
        if (!$this->canPrioritize($user) || $cuenta->status !== self::STATUS_PRIORIZADA) {
            throw new InvalidArgumentException('Solo se pueden desprogramar cuentas programadas, y quien prioriza los pagos.');
        }

        $cuenta->update([
            'status' => (float) $cuenta->paid_amount > 0 ? self::STATUS_PARCIAL : self::STATUS_PENDIENTE,
            'priority' => null,
            'scheduled_for' => null,
        ]);
        $this->log($cuenta, PagoMovimiento::ACTION_DESPROGRAMADA, $user, null, $comment);

        return $cuenta;
    }

    /**
     * Contabilidad registra el pago (total o abono) de una cuenta programada, con soporte de pago obligatorio.
     */
    public function pay(PagoCuenta $cuenta, array $data, User $user): PagoCuenta
    {
        if (!$this->canPay($user)) {
            throw new InvalidArgumentException('No tienes permiso para registrar pagos.');
        }
        if ($cuenta->status !== self::STATUS_PRIORIZADA) {
            throw new InvalidArgumentException('Solo se pueden pagar cuentas priorizadas y programadas.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0 || $amount > $cuenta->balance()) {
            throw new InvalidArgumentException('El valor pagado debe ser mayor a cero y no superar el saldo de $' . number_format($cuenta->balance(), 0, ',', '.') . '.');
        }

        if (empty($data['upload_token'])) {
            throw new InvalidArgumentException('Adjunta el soporte de pago para registrar el pago.');
        }
        $file = $this->uploads->forModule('Pagos')->claimUpload($data['upload_token'], $user, $this->supportFolder($cuenta));

        return DB::transaction(function () use ($cuenta, $data, $amount, $file, $user) {
            $cuenta = PagoCuenta::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();
            if ($cuenta->status !== self::STATUS_PRIORIZADA || $amount > $cuenta->balance()) {
                throw new InvalidArgumentException('La cuenta cambió mientras registrabas el pago. Vuelve a abrirla.');
            }

            $cuenta->paid_amount = (float) $cuenta->paid_amount + $amount;
            $this->refreshStatus($cuenta);
            $cuenta->save();

            $cuenta->movimientos()->create([
                'action' => PagoMovimiento::ACTION_PAGO,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'reference' => isset($data['reference']) ? trim($data['reference']) : null,
                'comment' => isset($data['comment']) ? trim($data['comment']) : null,
                'file_name' => $file['file_name'] ?? null,
                'file_path' => $file['file_path'] ?? null,
                'user_id' => $user->id,
            ]);

            return $cuenta;
        });
    }

    /**
     * Anula una cuenta manual o indirecta sin pagos (ej. registrada por error).
     */
    public function annul(PagoCuenta $cuenta, string $comment, User $user): PagoCuenta
    {
        if (!$this->canRegister($user) || !self::isAnnullable($cuenta) || (float) $cuenta->paid_amount > 0) {
            throw new InvalidArgumentException('Solo se pueden anular cuentas manuales o indirectas sin pagos.');
        }

        $cuenta->update(['status' => self::STATUS_ANULADA, 'priority' => null, 'scheduled_for' => null]);
        $this->log($cuenta, PagoMovimiento::ACTION_ANULADA, $user, null, $comment);

        return $cuenta;
    }

    /**
     * Anula las cuentas por pagar vigentes que generó un registro que se elimina por error (corte, orden, anticipo,
     * cuenta de cobro o factura indirecta). Si alguna ya tiene pagos no se anula nada: primero hay que revertir el
     * pago en tesorería. Debe correr dentro de la transacción de la eliminación.
     *
     * @param string $column corte_id | orden_id | anticipo_id | cuenta_cobro_id | id
     */
    public function annulForDeletedSource(string $column, int $id, string $reason, User $user): int
    {
        $cuentas = PagoCuenta::where($column, $id)->where('status', '!=', self::STATUS_ANULADA)->lockForUpdate()->get();
        $this->assertWithoutPayments($cuentas);

        foreach ($cuentas as $cuenta) {
            $this->annulDeleted($cuenta, $reason, $user);
        }

        return $cuentas->count();
    }

    /**
     * Reversa en cuentas por pagar una factura de corte aprobada por Contabilidad que se elimina:
     *  - Factura inicial: anula su cuenta (sin pagos).
     *  - Nota crédito: devuelve el valor descontado a la factura que afectaba.
     *  - Factura de reemplazo: anula la nueva y reactiva la anterior (si la nueva no recibió pagos propios).
     * Debe correr dentro de la transacción de la eliminación.
     */
    public function reverseCorteInvoice(CorteInvoice $invoice, string $reason, User $user): void
    {
        if ($invoice->accounting_status !== CorteInvoice::ACCOUNTING_APROBADA) {
            return; // Aún no llegó a cuentas por pagar
        }

        $related = $invoice->related_document
            ? PagoCuenta::where('corte_id', $invoice->corte_id)->where('document_number', $invoice->related_document)
                ->orderByDesc('id')->lockForUpdate()->first()
            : null;

        if ($invoice->document_type === CorteInvoice::TYPE_NOTA_CREDITO) {
            if ($related) {
                $related->adjustments = max(0, (float) $related->adjustments - (float) $invoice->amount);
                $this->recomputeStatus($related);
                $related->save();
                $this->log($related, PagoMovimiento::ACTION_NOTA_CREDITO, $user, -1 * (float) $invoice->amount,
                    "Se eliminó la nota crédito N.º {$invoice->invoice_number}; se reversa su descuento. Motivo: {$reason}");
            }

            return;
        }

        $cuenta = PagoCuenta::where('source_type', PagoCuenta::SOURCE_CORTE)->where('source_id', $invoice->id)
            ->where('status', '!=', self::STATUS_ANULADA)->lockForUpdate()->first();
        if (!$cuenta) {
            return;
        }

        $isReplacement = $invoice->document_type === CorteInvoice::TYPE_FACTURA_REEMPLAZO && $related && $related->status === self::STATUS_ANULADA;
        // En el reemplazo la nueva hereda los pagos de la anterior: solo bloquean los pagos hechos a la nueva
        $ownPayments = (float) $cuenta->paid_amount - ($isReplacement ? (float) $related->paid_amount : 0);
        if ($ownPayments > 0) {
            throw new InvalidArgumentException("La cuenta por pagar {$cuenta->document_number} ya tiene pagos registrados; revierte primero el pago en Cuentas por pagar.");
        }

        $this->annulDeleted($cuenta, $reason, $user);

        if ($isReplacement) {
            $related->status = self::STATUS_PENDIENTE;
            $this->recomputeStatus($related);
            $related->save();
            $this->log($related, PagoMovimiento::ACTION_REEMPLAZO, $user, null,
                "Se eliminó la factura de reemplazo {$invoice->invoice_number}; esta factura vuelve a estar vigente. Motivo: {$reason}");
        }
    }

    /**
     * @param iterable<PagoCuenta> $cuentas
     */
    public function assertWithoutPayments(iterable $cuentas): void
    {
        foreach ($cuentas as $cuenta) {
            if ($cuenta->status !== self::STATUS_ANULADA && (float) $cuenta->paid_amount > 0) {
                throw new InvalidArgumentException("La cuenta por pagar {$cuenta->document_number} ya tiene pagos registrados; revierte primero el pago en Cuentas por pagar.");
            }
        }
    }

    protected function annulDeleted(PagoCuenta $cuenta, string $reason, User $user): void
    {
        // source_id se libera (único por origen): si el registro se vuelve a cargar, genera su nueva cuenta.
        // corte_id / orden_id / anticipo_id / cuenta_cobro_id se conservan para la trazabilidad.
        $cuenta->update(['status' => self::STATUS_ANULADA, 'priority' => null, 'scheduled_for' => null, 'source_id' => null]);
        $this->log($cuenta, PagoMovimiento::ACTION_ANULADA, $user, null, "Anulada porque se eliminó el registro de origen. Motivo: {$reason}");
    }

    /**
     * Estado de una cuenta vigente según su saldo (también para reactivar una que estaba pagada o anulada).
     */
    protected function recomputeStatus(PagoCuenta $cuenta): void
    {
        if ($cuenta->status === self::STATUS_ANULADA) {
            return;
        }
        if ($cuenta->balance() <= 0) {
            $cuenta->fill(['status' => self::STATUS_PAGADA, 'paid_at' => $cuenta->paid_at ?? now()]);
        } elseif (in_array($cuenta->status, [self::STATUS_PAGADA, self::STATUS_PENDIENTE, self::STATUS_PARCIAL], true)) {
            $cuenta->fill(['status' => (float) $cuenta->paid_amount > 0 ? self::STATUS_PARCIAL : self::STATUS_PENDIENTE, 'paid_at' => null]);
        }
    }

    public function supportDownloadUrl(PagoMovimiento $movimiento): string
    {
        return $this->uploads->downloadUrl($movimiento->file_path, $movimiento->file_name);
    }

    public function requestUpload(string $fileName, int $fileSize, ?string $mime, User $user): array
    {
        return $this->uploads->requestUpload($fileName, $fileSize, $mime, $user, (int) config('pagos.max_upload_mb'));
    }

    /**
     * Carpeta del soporte de pago: junto al corte u orden de origen, o por frente y proveedor si es manual.
     */
    protected function supportFolder(PagoCuenta $cuenta): string
    {
        return match (true) {
            $cuenta->corte !== null => $this->corteStorage->folderOf($cuenta->corte) . '/03 Pagos',
            $cuenta->orden !== null => $this->ordenStorage->folderOf($cuenta->orden) . '/04 Pagos',
            default => implode('/', [
                trim(config('pagos.s3_root'), '/'),
                $this->uploads->cleanSegment($cuenta->frente),
                $this->uploads->cleanSegment($cuenta->supplier_name, 100),
            ]),
        };
    }

    /**
     * Estado según el saldo: sin saldo queda pagada; con abonos y aún programada sigue programada solo
     * hasta que se paga, después vuelve a "Abono parcial" para priorizar el resto.
     */
    protected function refreshStatus(PagoCuenta $cuenta): void
    {
        if ($cuenta->status === self::STATUS_ANULADA) {
            return;
        }

        if ($cuenta->balance() <= 0) {
            $cuenta->fill(['status' => self::STATUS_PAGADA, 'paid_at' => $cuenta->paid_at ?? now()]);
        } elseif ((float) $cuenta->paid_amount > 0 && $cuenta->status === self::STATUS_PRIORIZADA) {
            $cuenta->fill(['status' => self::STATUS_PARCIAL, 'priority' => null, 'scheduled_for' => null]);
        }
    }

    /**
     * Las que registra Contabilidad sin flujo de obra detrás: manuales e indirectas.
     */
    protected static function isAnnullable(PagoCuenta $cuenta): bool
    {
        return in_array($cuenta->source_type, [PagoCuenta::SOURCE_MANUAL, PagoCuenta::SOURCE_INDIRECTO], true);
    }

    protected function frenteFor(?string $towerName): string
    {
        return mb_strtoupper(trim($towerName ?: config('pagos.frente_indirectos')));
    }

    protected function log(PagoCuenta $cuenta, string $action, User $user, ?float $amount, ?string $comment): void
    {
        $cuenta->movimientos()->create([
            'action' => $action,
            'amount' => $amount,
            'comment' => $comment ? trim($comment) : null,
            'user_id' => $user->id,
        ]);
    }

    protected function present(PagoCuenta $c, User $user, bool $withDetail = false): array
    {
        $data = [
            'id' => $c->id,
            'source_type' => $c->source_type,
            'source_label' => self::SOURCE_LABELS[$c->source_type] ?? $c->source_type,
            'source_code' => $c->corte?->code ?? $c->orden?->code ?? $c->anticipo?->code ?? $c->cuentaCobro?->code,
            'corte_id' => $c->corte_id,
            'orden_id' => $c->orden_id,
            'anticipo_id' => $c->anticipo_id,
            'cuenta_cobro_id' => $c->cuenta_cobro_id,
            'project' => $c->project,
            'frente' => $c->frente,
            'supplier_nit' => $c->supplier_nit,
            'supplier_name' => $c->supplier_name,
            'document_number' => $c->document_number,
            'concept' => $c->concept,
            'amount' => (float) $c->amount,
            'adjustments' => (float) $c->adjustments,
            'paid_amount' => (float) $c->paid_amount,
            'balance' => $c->balance(),
            'status' => $c->status,
            'status_label' => self::STATUS_LABELS[$c->status] ?? $c->status,
            'priority' => $c->priority,
            'priority_label' => $c->priority ? self::PRIORITIES[$c->priority] : null,
            'scheduled_for' => $c->scheduled_for?->toDateString(),
            'prioritized_by' => $c->prioritizer?->name,
            'prioritized_at' => $c->prioritized_at?->toIso8601String(),
            'paid_at' => $c->paid_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'can_prioritize' => in_array($c->status, [self::STATUS_PENDIENTE, self::STATUS_PARCIAL, self::STATUS_PRIORIZADA], true) && $this->canPrioritize($user),
            'can_pay' => $c->status === self::STATUS_PRIORIZADA && $this->canPay($user),
            'can_annul' => self::isAnnullable($c) && (float) $c->paid_amount <= 0
                && $c->status !== self::STATUS_ANULADA && $this->canRegister($user),
        ];

        if ($withDetail) {
            $data['created_by'] = $c->creator?->name;
            $data['movimientos'] = $c->movimientos->sortByDesc('id')->map(fn (PagoMovimiento $m) => [
                'id' => $m->id,
                'action' => $m->action,
                'amount' => $m->amount !== null ? (float) $m->amount : null,
                'payment_date' => $m->payment_date?->toDateString(),
                'reference' => $m->reference,
                'comment' => $m->comment,
                'file_name' => $m->file_name,
                'user' => $m->user?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
