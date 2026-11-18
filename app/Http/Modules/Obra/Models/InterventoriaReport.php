<?php

namespace App\Http\Modules\Obra\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Informe semanal de interventoría leído desde su PDF.
 *
 * `data` guarda lo que devuelve tools/interventoria/parse_informe.py: presupuesto, capítulos con su control de
 * programación, temas de comité, actividades, observaciones y pólizas.
 */
class InterventoriaReport extends Model
{
    protected $table = 'interventoria_reports';

    protected $fillable = [
        'numero',
        'fecha',
        'file_path',
        'file_size',
        'file_last_modified',
        'data',
        'parser_version',
        'imported_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha' => 'date',
        'data' => 'array',
        'file_size' => 'integer',
        'file_last_modified' => 'integer',
        'parser_version' => 'integer',
        'imported_at' => 'datetime',
    ];
}
