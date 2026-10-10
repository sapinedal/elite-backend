<?php

namespace App\Http\Modules\Juridica\Models;

use Illuminate\Database\Eloquent\Model;

class PromiseAudit extends Model
{
    protected $table = 'promise_audits';

    protected $fillable = [
        'contract_number',
        'client_name',
        'status',
        'risk_score',
        'raw_text',
        'ai_analysis',
    ];

    protected $casts = [
        'ai_analysis' => 'array',
    ];
}
