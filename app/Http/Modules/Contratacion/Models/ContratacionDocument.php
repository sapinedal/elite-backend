<?php

namespace App\Http\Modules\Contratacion\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContratacionDocument extends Model
{
    protected $table = 'contratacion_documents';

    protected $fillable = [
        'contract_id',
        'folder',
        'document_type',
        'name',
        'path',
        'size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function contract()
    {
        return $this->belongsTo(ContratacionContract::class, 'contract_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
