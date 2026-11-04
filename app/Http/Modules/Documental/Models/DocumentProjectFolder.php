<?php

namespace App\Http\Modules\Documental\Models;

use App\Http\Modules\Configuracion\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * Carpeta del bucket asignada a un proyecto (ella y todo su contenido).
 */
class DocumentProjectFolder extends Model
{
    protected $table = 'document_project_folders';

    protected $fillable = [
        'project_id',
        'folder_path',
        'created_by',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
