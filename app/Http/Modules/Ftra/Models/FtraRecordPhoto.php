<?php

namespace App\Http\Modules\Ftra\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FtraRecordPhoto extends Model
{
    protected $table = 'ftra_record_photos';

    protected $fillable = [
        'ftra_record_id',
        'photo_path',
    ];

    protected $appends = ['photo_url'];

    public function record()
    {
        return $this->belongsTo(FtraRecord::class, 'ftra_record_id');
    }

    /**
     * Accesor para obtener el URL público de la foto.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }
        return null;
    }
}
