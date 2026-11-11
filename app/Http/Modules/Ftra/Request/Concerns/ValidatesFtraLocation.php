<?php

namespace App\Http\Modules\Ftra\Request\Concerns;

use App\Http\Modules\Configuracion\Models\Tower;
use Illuminate\Validation\Validator;

/**
 * El piso y el apartamento deben existir en la distribución de la torre (Administración › Proyectos),
 * y la torre debe pertenecer al proyecto del registro.
 */
trait ValidatesFtraLocation
{
    protected function validateLocation(Validator $validator, ?int $projectId, $towerId, $piso, $apartamento): void
    {
        if ($validator->errors()->hasAny(['tower_id', 'piso', 'apartamento']) || !$towerId) {
            return;
        }

        $tower = Tower::find($towerId);
        if (!$tower || ($projectId && $tower->project_id !== $projectId)) {
            $validator->errors()->add('tower_id', 'La torre no pertenece al proyecto.');
            return;
        }

        if (!$tower->hasUnits()) {
            $validator->errors()->add('tower_id', "La torre {$tower->name} no tiene pisos y apartamentos configurados. Configúralos en Administración › Proyectos.");
            return;
        }

        if (!$tower->hasUnit((int) $piso, (string) $apartamento)) {
            $validator->errors()->add('apartamento', "El apartamento {$apartamento} no existe en el piso {$piso} de {$tower->name}.");
        }
    }
}
