<?php

namespace App\Http\Modules\Juridica\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Ftra\Models\FtraContractor;

class Descargo extends Model
{
    protected $table = 'descargos';

    protected $fillable = [
        'contractor_id',
        'contractor_name_raw',
        'hearing_date',
        'observations',
        'status',
    ];

    protected $casts = [
        'hearing_date' => 'date',
    ];

    public function contractor()
    {
        return $this->belongsTo(FtraContractor::class, 'contractor_id');
    }
}
