<?php

namespace App\Http\Modules\Documental\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSetting extends Model
{
    protected $table = 'document_settings';

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'json',
    ];
}
