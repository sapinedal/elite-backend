<?php

namespace App\Http\Modules\Evaluaciones\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Modules\Plantillas\Models\KPI;

class EvaluationResult extends Model
{
    protected $fillable = [
        'evaluation_id',
        'kpi_id',
        'kpi_name',
        'kpi_weight',
        'kpi_target',
        'real_value',
        'score',
        'ai_analysis',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function kpi()
    {
        return $this->belongsTo(KPI::class);
    }
}
