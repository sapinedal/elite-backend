<?php

namespace App\Http\Modules\Documental\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enlace público permanente (no caduca) a un archivo de Gestión Documental.
 */
class DocumentShareLink extends Model
{
    protected $table = 'document_share_links';

    protected $fillable = [
        'token',
        'path',
        'created_by',
        'access_count',
        'last_accessed_at',
        'revoked_at',
    ];

    protected $casts = [
        'access_count' => 'integer',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** Enlaces del archivo exacto o, si es carpeta, de todo lo que contiene */
    public function scopeUnder(Builder $query, string $path): Builder
    {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $path) . '/%';

        return $query->where(fn (Builder $q) => $q->where('path', $path)->orWhere('path', 'like', $like));
    }
}
