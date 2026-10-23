<?php

namespace App\Http\Modules\Cortes\Request;

/**
 * Reenvío de un corte devuelto: mismos datos que al radicar, pero el archivo nuevo es opcional.
 */
class ResubmitCorteRequest extends StoreCorteRequest
{
    protected bool $fileRequired = false;
}
