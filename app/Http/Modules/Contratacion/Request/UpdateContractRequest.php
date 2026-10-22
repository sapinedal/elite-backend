<?php

namespace App\Http\Modules\Contratacion\Request;

class UpdateContractRequest extends StoreContractRequest
{
    protected function projectId(): ?int
    {
        return $this->route('contract')?->project_id;
    }

    protected function ignoredContractId(): ?int
    {
        return $this->route('contract')?->id;
    }
}
