<?php

namespace App\Services;

use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Str;

/**
 * Compara el cargo de un usuario con una lista de nombres de cargo (sin distinguir mayúsculas ni tildes),
 * para habilitar acciones por cargo sin amarrarlas a una persona.
 */
class PositionMatcher
{
    /**
     * @param list<string> $positions
     */
    public static function matches(User $user, array $positions): bool
    {
        $user->loadMissing('position:id,name');
        if (!$user->position || !$positions) {
            return false;
        }

        return collect($positions)->map(fn ($name) => self::comparable($name))->contains(self::comparable($user->position->name));
    }

    public static function comparable(string $value): string
    {
        return trim(Str::lower(Str::ascii($value)));
    }
}
