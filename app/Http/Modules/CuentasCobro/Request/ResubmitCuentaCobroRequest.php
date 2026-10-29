<?php

namespace App\Http\Modules\CuentasCobro\Request;

/**
 * Reenvío de una cuenta de cobro devuelta: mismos datos que al radicar, pero la cuenta de cobro nueva es opcional.
 */
class ResubmitCuentaCobroRequest extends StoreCuentaCobroRequest
{
    protected bool $fileRequired = false;
}
