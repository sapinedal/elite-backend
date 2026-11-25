<?php

namespace App\Http\Modules\Configuracion\Service;

use App\Http\Modules\Ftra\Models\FtraContractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administración de proveedores (ftra_contractors) desde Configuración.
 *
 * Un proveedor solo se elimina si ningún módulo lo referencia: las llaves foráneas son nullOnDelete
 * (los registros quedarían sin proveedor) o cascade en FTRA (se borrarían los registros). Si está en uso,
 * se debe desactivar.
 */
class ProveedorService
{
    /** Tablas que guardan contractor_id, con el nombre del módulo que se muestra al usuario */
    private const USAGES = [
        'contratacion_contracts' => 'Contratos',
        'contracts' => 'Contratos (jurídica)',
        'cortes' => 'Cortes',
        'anticipos' => 'Anticipos',
        'cuentas_cobro' => 'Cuentas de cobro',
        'facturacion_invoices' => 'Facturación',
        'pagos_cuentas' => 'Pagos',
        'ordenes_documents' => 'Órdenes',
        'descargos' => 'Descargos',
        'ftra_records' => 'Registros FTRA',
    ];

    /**
     * Listado paginado. Incluye active_count (sobre todo el filtro, no solo la página) para las tarjetas.
     */
    public function list(array $filters = []): array
    {
        $query = FtraContractor::query();

        if (!empty($filters['search'])) {
            $search = mb_strtolower(trim($filters['search']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(name) like ?', ["%{$search}%"])
                  ->orWhere('nit', 'like', "%{$search}%");
            });
        }

        $activeCount = (clone $query)->where('is_active', true)->count();
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        $page = $query->orderBy('name')->orderBy('id')->paginate($perPage);
        $counts = $this->usageCountsFor($page->getCollection()->pluck('id')->all());

        $page->setCollection($page->getCollection()->map(fn (FtraContractor $p) => [
            ...$p->only(['id', 'name', 'nit', 'email', 'phone', 'is_active', 'created_at']),
            'usage_count' => array_sum($counts[$p->id] ?? []),
        ]));

        return [...$page->toArray(), 'active_count' => $activeCount];
    }

    public function update(FtraContractor $proveedor, array $data): FtraContractor
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }
        if (array_key_exists('nit', $data)) {
            $data['nit'] = $data['nit'] !== null && trim($data['nit']) !== '' ? trim($data['nit']) : null;
        }

        $duplicate = null;
        if (!empty($data['name']) || !empty($data['nit'])) {
            $duplicate = FtraContractor::query()
                ->where('id', '!=', $proveedor->id)
                ->where(function ($q) use ($data) {
                    if (!empty($data['name'])) {
                        $q->orWhereRaw('lower(name) = ?', [mb_strtolower($data['name'])]);
                    }
                    if (!empty($data['nit'])) {
                        $q->orWhere('nit', $data['nit']);
                    }
                })
                ->first(['name', 'nit']);
        }

        if ($duplicate) {
            throw new \DomainException(
                "Ya existe el proveedor {$duplicate->name}" . ($duplicate->nit ? " (NIT {$duplicate->nit})" : '') . '.'
            );
        }

        $proveedor->update($data);

        return $proveedor->fresh();
    }

    /**
     * @throws \DomainException si el proveedor tiene registros asociados
     */
    public function delete(FtraContractor $proveedor): void
    {
        $usages = $this->usageCountsFor([$proveedor->id])[$proveedor->id] ?? [];

        if ($usages) {
            $detail = collect($usages)
                ->map(fn ($count, $table) => self::USAGES[$table] . " ({$count})")
                ->implode(', ');

            throw new \DomainException(
                "No se puede eliminar {$proveedor->name}: tiene registros asociados en {$detail}. Desactívalo para que no aparezca en los formularios."
            );
        }

        $proveedor->delete();
    }

    /**
     * Conteo de registros por proveedor y tabla: [contractor_id => [tabla => n]]. Omite los ceros.
     */
    private function usageCountsFor(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $result = [];
        foreach (array_keys(self::USAGES) as $table) {
            if (!Schema::hasColumn($table, 'contractor_id')) {
                continue;
            }

            DB::table($table)
                ->whereIn('contractor_id', $ids)
                ->groupBy('contractor_id')
                ->select('contractor_id', DB::raw('count(*) as total'))
                ->get()
                ->each(function ($row) use (&$result, $table) {
                    $result[$row->contractor_id][$table] = (int) $row->total;
                });
        }

        return $result;
    }
}
