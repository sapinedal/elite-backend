<?php

namespace App\Http\Modules\Juridica\Models;

use Illuminate\Database\Eloquent\Model;

class Desistimiento extends Model
{
    protected $table = 'desistimientos';

    protected $fillable = [
        'client_name',
        'apartment',
        'refund_status',
        'amount',
    ];
}
