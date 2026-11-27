<?php

namespace App\Http\Modules\Cortes\Models;

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Corte extends Model
{
    use SoftDeletes;

    protected $table = 'cortes';

    // Los cortes se numeran por contrato: CORTE 1, CORTE 2... (los anteriores conservan su código libre)
    public const CODE_PREFIX = 'CORTE';

    protected $fillable = [
        'code',
        'project_id',
        'tower_id',
        'contractor_id',
        'contract_id',
        'status',
        'current_stage',
        'returned_stage',
        'interventor_rejected',
        'storage_path',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'created_by',
        'approved_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'interventor_rejected' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tower()
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(CorteHistory::class, 'corte_id')->orderBy('id');
    }

    public function invoices()
    {
        return $this->hasMany(CorteInvoice::class, 'corte_id')->orderByDesc('id');
    }

    /**
     * Facturas vigentes: las del corte (inicial o de reemplazo) que ninguna nota crédito afecta. Si un corte facturado
     * se queda sin facturas vigentes (todas anuladas con nota crédito), admite de nuevo una factura.
     */
    public function hasOpenInvoices(): bool
    {
        return !$this->allInvoicesCredited();
    }

    /**
     * Todas las facturas del corte (inicial o de reemplazo) tienen una nota crédito que las afecta. Un corte sin
     * facturas no cuenta como anulado.
     */
    /**
     * Asocia facturas al corte quien lo radicó o quien puede eliminar sus facturas (cortes.eliminar) en el proyecto:
     * así quien corrige una factura mal cargada puede asociar la nueva.
     */
    public function canAssociateInvoice(User $user): bool
    {
        return $this->created_by === $user->id
            || ($user->can('cortes.eliminar') && $user->canAccessProject($this->project_id));
    }

    public function allInvoicesCredited(): bool
    {
        $invoices = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->get();
        $credited = $invoices->where('document_type', CorteInvoice::TYPE_NOTA_CREDITO)
            ->pluck('related_document')->filter()->map(fn ($n) => mb_strtoupper(trim($n)));
        $facturas = $invoices->where('document_type', '!=', CorteInvoice::TYPE_NOTA_CREDITO);

        return $facturas->isNotEmpty()
            && $facturas->every(fn (CorteInvoice $i) => $credited->contains(mb_strtoupper(trim($i->invoice_number))));
    }

    public static function codeFor(int $number): string
    {
        return self::CODE_PREFIX . ' ' . $number;
    }

    /**
     * Número de un código "CORTE N" (acepta también "corte 3" o "3"); null si no tiene ese formato.
     */
    public static function numberOf(?string $code): ?int
    {
        return preg_match('/^\s*(?:corte\s*)?(\d{1,6})\s*$/i', (string) $code, $m) && (int) $m[1] > 0 ? (int) $m[1] : null;
    }

    /**
     * Nombre para mostrar: "CORTE 3"; los cortes anteriores a la numeración, "Corte 6634".
     */
    public function label(): string
    {
        return self::numberOf($this->code) !== null && str_starts_with($this->code, self::CODE_PREFIX)
            ? $this->code
            : "Corte {$this->code}";
    }

    /**
     * Nombre con su contrato, porque el número se repite entre contratos: "CORTE 3 · Contrato 2759".
     */
    public function reference(): string
    {
        $contract = $this->contract?->contract_code;

        return $this->label() . ($contract ? " · Contrato {$contract}" : '');
    }

    /**
     * Limita a los proyectos a los que el usuario tiene acceso (todos si tiene proyectos.ver_todos).
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $projectIds = $user->accessibleProjectIds();

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }
}
