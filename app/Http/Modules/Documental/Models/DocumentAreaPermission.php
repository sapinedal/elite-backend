<?php

namespace App\Http\Modules\Documental\Models;

use App\Http\Modules\Configuracion\Models\Area;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAreaPermission extends Model
{
    use HasFactory;

    protected $table = 'document_area_permissions';

    protected $fillable = [
        'area_id',
        'folder_path',
        'can_read',
        'can_upload',
        'can_create_folder',
        'can_delete',
    ];

    protected $casts = [
        'can_read' => 'boolean',
        'can_upload' => 'boolean',
        'can_create_folder' => 'boolean',
        'can_delete' => 'boolean',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
