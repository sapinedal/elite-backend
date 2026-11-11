<?php

namespace App\Http\Modules\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class Tower extends Model
{
    protected $table = 'towers';

    protected $fillable = [
        'project_id',
        'name',
        'code',
        'description',
        'floors',
        'apartments_per_floor',
    ];

    protected $casts = [
        'floors' => 'integer',
        'apartments_per_floor' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** La torre tiene pisos y apartamentos configurados (las etapas como Urbanismo no). */
    public function hasUnits(): bool
    {
        return $this->floors > 0 && $this->apartments_per_floor > 0;
    }

    /** Número del apartamento: piso × 100 + consecutivo (piso 12, apto 1 → "1201"). */
    public static function unitNumber(int $floor, int $index): string
    {
        return (string) ($floor * 100 + $index);
    }

    public function hasUnit(int $floor, string $apartment): bool
    {
        if (!$this->hasUnits() || $floor < 1 || $floor > $this->floors) {
            return false;
        }

        $index = (int) $apartment - $floor * 100;

        return ctype_digit($apartment) && $index >= 1 && $index <= $this->apartments_per_floor;
    }
}
