<?php

namespace App\Http\Modules\Documental\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Clave de escritorio de un usuario para la unidad de red de Gestión Documental (WebDAV).
 */
class DocumentDesktopKey extends Model
{
    protected $table = 'document_desktop_keys';

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'last_four',
        'last_used_at',
        'last_used_ip',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
