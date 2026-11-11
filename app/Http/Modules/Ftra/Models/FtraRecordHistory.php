<?php

namespace App\Http\Modules\Ftra\Models;

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class FtraRecordHistory extends Model
{
    public const ACTION_REGISTRADO = 'registrado';
    public const ACTION_REENVIADO = 'reenviado';
    public const ACTION_APROBADO = 'aprobado';
    public const ACTION_DEVUELTO = 'devuelto';

    protected $table = 'ftra_record_history';

    protected $fillable = ['ftra_record_id', 'user_id', 'action', 'stage', 'comment'];

    public function record()
    {
        return $this->belongsTo(FtraRecord::class, 'ftra_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
