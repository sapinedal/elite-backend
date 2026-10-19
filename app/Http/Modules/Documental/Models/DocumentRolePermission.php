<?php

namespace App\Http\Modules\Documental\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class DocumentRolePermission extends Model
{
    protected $table = 'document_role_permissions';

    protected $fillable = [
        'role_id',
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

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
