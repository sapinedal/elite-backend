<?php

namespace App\Http\Modules\Anticipos\Request;

/**
 * Reenvío de un anticipo devuelto: mismos datos que al radicar, pero la cuenta de cobro nueva es opcional.
 */
class ResubmitAnticipoRequest extends StoreAnticipoRequest
{
    protected bool $fileRequired = false;
}
