<?php

namespace App\Http\Modules\Ftra\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FtraFormat extends Model
{
    protected $table = 'ftra_formats';

    protected $fillable = [
        'name',
        'code',
        'version',
        'description',
        'pdf_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['pdf_url'];

    /**
     * Accesor para obtener el URL público del PDF.
     */
    public function getPdfUrlAttribute(): ?string
    {
        if ($this->pdf_path) {
            return Storage::disk('public')->url($this->pdf_path);
        }
        return null;
    }
}
