<?php

namespace App\Http\Modules\Juridica\Service;

use App\Http\Modules\Juridica\Models\Contract;
use App\Http\Modules\Juridica\Models\Policy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContractService
{
    /**
     * Get filtered contracts with policies, contractor, project, and tower
     */
    public function getFilteredContracts(?int $projectId = null, ?int $towerId = null, ?string $category = null, ?string $search = null): Collection
    {
        $query = Contract::with(['contractor', 'policies', 'project', 'tower']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($towerId) {
            $query->where('tower_id', $towerId);
        }

        if ($category && $category !== 'all') {
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                  ->orWhereHas('tower', function ($tq) use ($category) {
                      $tq->where('name', 'LIKE', "%{$category}%");
                  });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('contractor_name_raw', 'LIKE', "%{$search}%")
                  ->orWhere('nro', 'LIKE', "%{$search}%")
                  ->orWhere('object', 'LIKE', "%{$search}%")
                  ->orWhere('type', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->get();
    }

    /**
     * Create a new contract with optional policy
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $contract = Contract::create([
                'nro' => $data['nro'],
                'project_id' => $data['project_id'] ?? null,
                'tower_id' => $data['tower_id'] ?? null,
                'contractor_id' => $data['contractor_id'] ?? null,
                'contractor_name_raw' => $data['contractor_name_raw'],
                'type' => $data['type'],
                'category' => $data['category'] ?? 'General',
                'object' => $data['object'] ?? null,
                'amount' => $data['amount'] ?? 0,
                'status' => $data['status'] ?? 'Vigente',
                'drive_link' => $data['drive_link'] ?? null,
            ]);

            if (!empty($data['policy']) && is_array($data['policy'])) {
                Policy::create([
                    'contract_id' => $contract->id,
                    'policy_number' => $data['policy']['policy_number'],
                    'insurance_company' => $data['policy']['insurance_company'],
                    'insured_value' => $data['policy']['insured_value'] ?? $contract->amount,
                    'end_date' => $data['policy']['end_date'] ?? null,
                ]);
            }

            return $contract->load(['policies', 'project', 'tower']);
        });
    }

    /**
     * Update existing contract and policy
     */
    public function updateContract(int $id, array $data): Contract
    {
        return DB::transaction(function () use ($id, $data) {
            $contract = Contract::findOrFail($id);
            $contract->update([
                'nro' => $data['nro'] ?? $contract->nro,
                'project_id' => isset($data['project_id']) ? $data['project_id'] : $contract->project_id,
                'tower_id' => isset($data['tower_id']) ? $data['tower_id'] : $contract->tower_id,
                'contractor_id' => isset($data['contractor_id']) ? $data['contractor_id'] : $contract->contractor_id,
                'contractor_name_raw' => $data['contractor_name_raw'] ?? $contract->contractor_name_raw,
                'type' => $data['type'] ?? $contract->type,
                'category' => $data['category'] ?? $contract->category,
                'object' => $data['object'] ?? $contract->object,
                'amount' => $data['amount'] ?? $contract->amount,
                'status' => $data['status'] ?? $contract->status,
                'drive_link' => $data['drive_link'] ?? $contract->drive_link,
            ]);

            if (isset($data['policy'])) {
                $contract->policies()->delete();
                if (!empty($data['policy']) && is_array($data['policy'])) {
                    Policy::create([
                        'contract_id' => $contract->id,
                        'policy_number' => $data['policy']['policy_number'],
                        'insurance_company' => $data['policy']['insurance_company'],
                        'insured_value' => $data['policy']['insured_value'] ?? $contract->amount,
                        'end_date' => $data['policy']['end_date'] ?? null,
                    ]);
                }
            }

            return $contract->fresh(['policies', 'project', 'tower']);
        });
    }

    /**
     * Delete contract by ID
     */
    public function deleteContract(int $id): bool
    {
        $contract = Contract::findOrFail($id);
        return $contract->delete();
    }

    /**
     * Get summary metrics for contracts
     */
    public function getContractMetrics(?int $projectId = null): array
    {
        $query = Contract::query();
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $totalContracts = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');
        $alertsCount = (clone $query)->where('status', 'Por Vencer')->count();
        $activeCount = (clone $query)->where('status', 'Vigente')->count();

        return [
            'total_contracts' => $totalContracts,
            'total_amount' => $totalAmount,
            'alerts_count' => $alertsCount,
            'active_count' => $activeCount,
        ];
    }
}
