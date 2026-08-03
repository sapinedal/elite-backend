<?php

namespace App\Http\Modules\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = [
        'code',
        'name',
        'subtitle',
        'description',
        'total_budget',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_budget' => 'float',
    ];
}
