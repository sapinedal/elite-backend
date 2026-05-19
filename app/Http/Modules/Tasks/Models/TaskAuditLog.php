<?php

namespace App\Http\Modules\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class TaskAuditLog extends Model
{
    /**
     * Campos rellenables de auditoría.
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'changes',
    ];

    /**
     * Conversión nativa a array asociativo de PHP.
     * 
     * Ventaja técnica: Al definir 'array', nos ahorramos hacer json_decode() y json_encode() 
     * de forma manual en nuestro código. Laravel lo hace tras bambalinas, garantizando
     * que podamos manipular los cambios como una lista asociativa de PHP normal.
     */
    protected $casts = [
        'changes' => 'array',
    ];

    /**
     * Relación inversa: Este log de auditoría pertenece a una tarea.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relación: El colaborador o administrador que provocó esta entrada de auditoría.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
