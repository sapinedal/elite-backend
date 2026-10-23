<?php

namespace App\Http\Modules\Contratacion\Service;

use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(protected ContractStorageService $storage)
    {
    }

    /**
     * Relaciones que necesita el listado (incluye documentos para calcular faltantes sin N+1).
     */
    protected function listRelations(): array
    {
        return [
            'tower:id,name,code',
            'contractType:id,name',
            'policies',
            'documents:id,contract_id,folder,document_type',
        ];
    }

    protected function baseQuery(User $user, ?int $projectId): Builder
    {
        $query = ContratacionContract::query()->accessibleBy($user);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query;
    }

    /**
     * Listado paginado. Las alertas (adjuntos faltantes / pólizas por vencer) se calculan en PHP, por eso
     * el filtro de alertas y la paginación se aplican sobre la colección ya presentada.
     */
    public function listContracts(User $user, ?int $projectId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($user, $projectId)->with($this->listRelations());

        if (!empty($filters['tower_id'])) {
            $query->where('tower_id', $filters['tower_id']);
        }
        if (!empty($filters['contract_type_id'])) {
            $query->where('contract_type_id', $filters['contract_type_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('contract_code', 'ilike', $search)
                  ->orWhere('contractor_name', 'ilike', $search)
                  ->orWhere('object', 'ilike', $search)
                  ->orWhere('consecutive', 'ilike', $search);
            });
        }

        $contracts = $query->orderBy('tower_id')->orderBy('consecutive')->get()
            ->map(fn (ContratacionContract $contract) => $this->present($contract));

        $contracts = match ($filters['alert'] ?? null) {
            'missing' => $contracts->filter(fn (array $c) => $c['missing_documents']),
            'policies' => $contracts->filter(fn (array $c) => $c['expiring_policies']),
            default => !empty($filters['alerts_only']) && filter_var($filters['alerts_only'], FILTER_VALIDATE_BOOLEAN)
                ? $contracts->filter(fn (array $c) => $c['missing_documents'] || $c['expiring_policies'])
                : $contracts,
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return new LengthAwarePaginator(
            $contracts->forPage($page, $perPage)->values(),
            $contracts->count(),
            $perPage,
            $page
        );
    }

    public function getContract(ContratacionContract $contract): array
    {
        $contract->load([
            'tower:id,name,code',
            'contractType:id,name',
            'policies',
            'documents.uploader:id,name',
            'creator:id,name',
        ]);

        return $this->present($contract, true);
    }

    /**
     * Resumen de alertas del proyecto: adjuntos faltantes y pólizas próximas a vencer.
     */
    public function alerts(User $user, ?int $projectId): array
    {
        $contracts = $this->baseQuery($user, $projectId)->with($this->listRelations())->get();

        $missing = [];
        $expiring = [];
        foreach ($contracts as $contract) {
            $presented = $this->present($contract);
            if ($presented['missing_documents']) {
                $missing[] = [
                    'contract_id' => $contract->id,
                    'contract_code' => $contract->contract_code,
                    'contractor_name' => $contract->contractor_name,
                    'missing' => array_column($presented['missing_documents'], 'label'),
                ];
            }
            foreach ($presented['expiring_policies'] as $policy) {
                $expiring[] = [
                    'contract_id' => $contract->id,
                    'contract_code' => $contract->contract_code,
                    'contractor_name' => $contract->contractor_name,
                    ...$policy,
                ];
            }
        }

        usort($expiring, fn ($a, $b) => $a['days_to_expire'] <=> $b['days_to_expire']);

        return [
            'missing_documents' => $missing,
            'expiring_policies' => $expiring,
            'policy_alert_days' => config('contratacion.policy_alert_days'),
        ];
    }

    /**
     * Crea el contrato, sus pólizas y su estructura de carpetas en S3.
     * Si S3 falla se revierte el registro para no dejar contratos sin carpeta.
     */
    public function createContract(array $data, User $user): ContratacionContract
    {
        return DB::transaction(function () use ($data, $user) {
            $policies = $data['policies'] ?? [];
            unset($data['policies']);

            $contract = ContratacionContract::create([...$data, 'created_by' => $user->id]);
            $this->syncPolicies($contract, $policies);

            $contract->update(['s3_path' => $this->storage->createContractFolder($contract)]);

            return $contract;
        });
    }

    /**
     * Actualiza los datos del contrato. La carpeta de S3 se conserva aunque cambie el frente
     * o el contratista, para no mover archivos ya cargados.
     */
    public function updateContract(ContratacionContract $contract, array $data): ContratacionContract
    {
        return DB::transaction(function () use ($contract, $data) {
            $hasPolicies = array_key_exists('policies', $data);
            $policies = $data['policies'] ?? [];
            unset($data['policies']);

            $contract->update($data);
            if ($hasPolicies) {
                $this->syncPolicies($contract, $policies);
            }

            return $contract;
        });
    }

    /**
     * Elimina el contrato de la aplicación (soft delete). Los archivos se conservan en S3.
     */
    public function deleteContract(ContratacionContract $contract): void
    {
        $contract->delete();
    }

    /**
     * Sincroniza las pólizas: actualiza las que llegan con id, crea las nuevas y elimina las ausentes.
     */
    protected function syncPolicies(ContratacionContract $contract, array $policies): void
    {
        $keepIds = [];

        foreach ($policies as $policyData) {
            $attributes = [
                'policy_type' => $policyData['policy_type'],
                'policy_number' => $policyData['policy_number'] ?? null,
                'insurer' => $policyData['insurer'] ?? null,
                'expires_at' => $policyData['expires_at'],
            ];

            $policy = !empty($policyData['id'])
                ? $contract->policies()->whereKey($policyData['id'])->first()
                : null;

            if ($policy) {
                $policy->fill($attributes);
                // Si cambia la vigencia se vuelve a notificar cuando entre en la ventana de alerta
                if ($policy->isDirty('expires_at')) {
                    $policy->alert_sent_at = null;
                }
                $policy->save();
            } else {
                $policy = $contract->policies()->create($attributes);
            }

            $keepIds[] = $policy->id;
        }

        $contract->policies()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Respuesta del contrato con sus alertas calculadas.
     */
    protected function present(ContratacionContract $contract, bool $withDocuments = false): array
    {
        $alertDays = config('contratacion.policy_alert_days');

        $policies = $contract->policies->map(fn (ContratacionPolicy $policy) => [
            'id' => $policy->id,
            'policy_type' => $policy->policy_type,
            'policy_number' => $policy->policy_number,
            'insurer' => $policy->insurer,
            'expires_at' => $policy->expires_at?->format('Y-m-d'),
            'days_to_expire' => $policy->daysToExpire(),
            'alert_sent_at' => $policy->alert_sent_at?->toIso8601String(),
        ]);

        $data = [
            'id' => $contract->id,
            'project_id' => $contract->project_id,
            'tower_id' => $contract->tower_id,
            'tower' => $contract->tower,
            'contract_type_id' => $contract->contract_type_id,
            'contract_type' => $contract->contractType,
            'contract_code' => $contract->contract_code,
            'consecutive' => $contract->consecutive,
            'contractor_name' => $contract->contractor_name,
            'object' => $contract->object,
            'signed_at' => $contract->signed_at?->format('Y-m-d'),
            'start_act_date' => $contract->start_act_date?->format('Y-m-d'),
            'estimated_end_date' => $contract->estimated_end_date?->format('Y-m-d'),
            'status' => $contract->status,
            's3_path' => $contract->s3_path,
            'policies' => $policies->values(),
            'missing_documents' => ContractDocumentCatalog::missingFor($contract),
            'expiring_policies' => $policies->filter(fn ($p) => $p['days_to_expire'] <= $alertDays)->values()->all(),
            'documents_count' => $contract->documents->count(),
            'created_at' => $contract->created_at?->toIso8601String(),
        ];

        if ($withDocuments) {
            $data['documents'] = $contract->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'folder' => $doc->folder,
                'document_type' => $doc->document_type,
                'name' => $doc->name,
                'size' => $doc->size,
                'mime_type' => $doc->mime_type,
                'uploaded_by' => $doc->uploader?->name,
                'created_at' => $doc->created_at?->toIso8601String(),
            ])->values();
            $data['created_by'] = $contract->creator?->name;
        }

        return $data;
    }
}
