<?php

namespace App\Http\Modules\Juridica\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'nro',
        'project_id',
        'tower_id',
        'contract_type_id',
        'contractor_id',
        'contractor_name_raw',
        'type',
        'category',
        'object',
        'amount',
        'status',
        'drive_link',
    ];

    public function contractType()
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

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

    public function policies()
    {
        return $this->hasMany(Policy::class, 'contract_id');
    }
}
