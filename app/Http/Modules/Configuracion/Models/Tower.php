<?php

namespace App\Http\Modules\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class Tower extends Model
{
    protected $table = 'towers';

    protected $fillable = [
        'project_id',
        'name',
        'code',
        'description',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
