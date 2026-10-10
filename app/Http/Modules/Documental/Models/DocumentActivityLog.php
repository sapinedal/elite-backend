<?php

namespace App\Http\Modules\Documental\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentActivityLog extends Model
{
    use HasFactory;

    protected $table = 'document_activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'path',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
