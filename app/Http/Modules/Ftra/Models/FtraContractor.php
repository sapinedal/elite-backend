<?php

namespace App\Http\Modules\Ftra\Models;

use Illuminate\Database\Eloquent\Model;

class FtraContractor extends Model
{
    protected $table = 'ftra_contractors';

    protected $fillable = [
        'name',
        'nit',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
