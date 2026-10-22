<?php

namespace App\Http\Modules\Contratacion\Request;

use App\Http\Middleware\ResolveActiveProject;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Models\ContratacionPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Proyecto al que pertenece el contrato: el activo al crear, el del contrato al editar.
     */
    protected function projectId(): ?int
    {
        return $this->attributes->get(ResolveActiveProject::ATTRIBUTE);
    }

    protected function ignoredContractId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        $projectId = $this->projectId();

        return [
            'tower_id' => ['required', 'integer', Rule::exists('towers', 'id')->where('project_id', $projectId)],
            'contract_type_id' => ['required', 'integer', Rule::exists('contract_types', 'id')],
            'contract_code' => [
                'required', 'string', 'max:100',
                Rule::unique('contratacion_contracts', 'contract_code')
                    ->where('project_id', $projectId)
                    ->whereNull('deleted_at')
                    ->ignore($this->ignoredContractId()),
            ],
            'consecutive' => 'required|string|max:50',
            'contractor_name' => 'required|string|max:255',
            'object' => 'required|string|max:5000',
            'signed_at' => 'nullable|date',
            'start_act_date' => 'nullable|date',
            'estimated_end_date' => 'nullable|date|after_or_equal:start_act_date',
            'status' => ['required', Rule::in(ContratacionContract::STATUSES)],

            'policies' => 'sometimes|array',
            'policies.*.id' => 'nullable|integer',
            'policies.*.policy_type' => ['required', Rule::in(ContratacionPolicy::POLICY_TYPES)],
            'policies.*.policy_number' => 'nullable|string|max:100',
            'policies.*.insurer' => 'nullable|string|max:255',
            'policies.*.expires_at' => 'required|date',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (!$this->projectId()) {
                    $validator->errors()->add('project_id', 'Selecciona un proyecto activo para registrar el contrato.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'tower_id' => 'frente de obra',
            'contract_type_id' => 'tipo de contrato',
            'contract_code' => 'ID del contrato',
            'consecutive' => 'consecutivo',
            'contractor_name' => 'nombre del contratista',
            'object' => 'objeto del contrato',
            'signed_at' => 'fecha de firma',
            'start_act_date' => 'fecha del acta de inicio',
            'estimated_end_date' => 'fecha de terminación estimada',
            'status' => 'estado del contrato',
            'policies.*.policy_type' => 'tipo de póliza',
            'policies.*.expires_at' => 'fecha de vigencia de la póliza',
        ];
    }
}
