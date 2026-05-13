<?php

namespace App\Http\Modules\Evaluaciones\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class EvaluationVersion extends Model
{
    protected $fillable = [
        'evaluation_id',
        'snapshot',
        'status_at_moment',
        'changed_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
