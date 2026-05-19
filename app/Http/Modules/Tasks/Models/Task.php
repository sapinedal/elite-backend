<?php

namespace App\Http\Modules\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Configuracion\Models\Area;

class Task extends Model
{
    /**
     * Campos rellenables de forma masiva (Mass Assignment Protection).
     * 
     * Ventaja técnica: Protege la base de datos contra inyecciones maliciosas de campos 
     * en peticiones HTTP masivas, forzando la declaración explícita de lo que es editable.
     */
    protected $fillable = [
        'title',
        'priority',
        'status',
        'requested_by_id',
        'responsible_id',
        'area_id',
        'start_date',
        'scheduled_end_date',
        'actual_end_date',
    ];

    /**
     * Conversión automática de tipos (Casting).
     * 
     * Ventaja: Laravel transformará automáticamente estas cadenas de fecha de la base de datos
     * en instancias de Carbon\Carbon, facilitando la comparación y el formateo de fechas.
     */
    protected $casts = [
        'start_date' => 'date',
        'scheduled_end_date' => 'date',
        'actual_end_date' => 'date',
    ];

    /**
     * Relación: La tarea fue creada/solicitada por un usuario.
     * 
     * Se especifica 'requested_by_id' ya que no sigue el nombre estándar de la tabla 'user_id'.
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * Relación: El usuario que se encargará de ejecutar la tarea.
     */
    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * Relación: El departamento o área organizacional a la que afecta esta tarea.
     */
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * Relación: Una tarea acumula múltiples notas/comentarios rápidos de dailys.
     * 
     * Ordenamos por defecto del más reciente al más antiguo para un mejor timeline.
     */
    public function observations()
    {
        return $this->hasMany(TaskObservation::class)->orderBy('created_at', 'desc');
    }

    /**
     * Relación: Historial completo de cambios y modificaciones sobre esta tarea.
     */
    public function auditLogs()
    {
        return $this->hasMany(TaskAuditLog::class)->orderBy('created_at', 'desc');
    }
}
