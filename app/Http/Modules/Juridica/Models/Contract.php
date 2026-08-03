<?php

namespace App\Http\Modules\Juridica\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Ftra\Models\FtraContractor;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'nro',
        'contractor_id',
        'contractor_name_raw',
        'type',
        'category',
        'object',
        'amount',
        'status',
        'drive_link',
    ];

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }

    public function policies()
    {
        return $this->hasMany(Policy::class, 'contract_id');
    }
}
