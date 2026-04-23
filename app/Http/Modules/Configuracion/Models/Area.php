<?php

namespace App\Http\Modules\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['name', 'description'];

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
