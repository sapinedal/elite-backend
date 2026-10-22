<?php

namespace App\Http\Modules\Configuracion\Models;

use App\Http\Modules\Tasks\Models\Task;
use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = [
        'code',
        'name',
        'subtitle',
        'description',
        'contracts_folder',
        'total_budget',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_budget' => 'float',
    ];

    public function towers()
    {
        return $this->hasMany(Tower::class, 'project_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_id');
    }
}
