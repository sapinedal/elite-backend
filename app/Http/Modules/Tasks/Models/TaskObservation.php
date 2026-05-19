<?php

namespace App\Http\Modules\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class TaskObservation extends Model
{
    /**
     * Campos rellenables de la observación.
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'observation',
    ];

    /**
     * Relación inversa: Una observación pertenece a una sola tarea.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relación: Quién redactó e ingresó esta nota del daily.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
