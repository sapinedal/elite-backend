<?php

namespace App\Http\Modules\Evaluaciones\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Users\Models\User;

class Evaluation extends Model
{
    protected $fillable = [
        'user_id',
        'evaluador_id',
        'month',
        'year',
        'total_score',
        'status',
        'general_analysis',
    ];

    protected $casts = [
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function results()
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
