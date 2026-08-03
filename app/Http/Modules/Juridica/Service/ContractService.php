<?php

namespace App\Http\Modules\Juridica\Service;

use App\Http\Modules\Juridica\Models\Contract;
use Illuminate\Database\Eloquent\Collection;

class ContractService
{
    /**
     * Get filtered contracts with policies and contractors
     */
    public function getFilteredContracts(?string $category = null, ?string $search = null): Collection
    {
        $query = Contract::with(['contractor', 'policies']);

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('contractor_name_raw', 'LIKE', "%{$search}%")
                  ->orWhere('nro', 'LIKE', "%{$search}%")
                  ->orWhere('object', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->get();
    }

    /**
     * Get summary metrics for contracts and policies
     */
    public function getContractMetrics(): array
    {
        $totalContracts = Contract::count();
        $totalAmount = Contract::sum('amount');
        $torre2Count = Contract::where('category', 'torre2')->count();
        $urbanismoCount = Contract::where('category', 'urbanismo')->count();
        $alertsCount = Contract::where('status', 'Por Vencer')->count();

        return [
            'total_contracts' => $totalContracts,
            'total_amount' => $totalAmount,
            'torre2_count' => $torre2Count,
            'urbanismo_count' => $urbanismoCount,
            'alerts_count' => $alertsCount,
            'presupuesto_total' => 22454184688,
            'ejecutado_fisico' => 10125195768,
            'saldo_ejecutar' => 12328988919,
            'fecha_entrega' => '2027-02-28'
        ];
    }
}
