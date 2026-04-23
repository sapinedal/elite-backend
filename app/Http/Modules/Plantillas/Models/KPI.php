<?php

namespace App\Http\Modules\Plantillas\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class KPI extends Model
{
    protected $table = 'kpis';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'indicators',
        'formula',
        'target',
        'unit',
        'stage',
        'weight',
        'incidence',
        'lower_is_better',
    ];

    protected $casts = [
        'indicators' => 'array',
        'lower_is_better' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
