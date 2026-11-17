<?php

namespace App\Http\Modules\Documental\Dav;

use Sabre\DAV\TemporaryFileFilterPlugin;

/**
 * Archivos basura que crean Windows, macOS y Office al navegar o editar (._*, .DS_Store, Thumbs.db, ~$doc.docx…):
 * se guardan en una carpeta temporal del servidor y nunca llegan a Spaces ni al registro de actividad.
 */
class DavTemporaryFilesPlugin extends TemporaryFileFilterPlugin
{
    public $temporaryFilePatterns = [
        '/^\._(.*)$/',          // macOS: resource forks
        '/^\.DS_Store$/',       // macOS: ajustes de carpeta
        '/^\.localized$/',
        '/^\.(Spotlight-V100|Trashes|fseventsd|metadata_never_index)/',
        '/^desktop\.ini$/i',    // Windows: ajustes de carpeta
        '/^Thumbs\.db$/i',      // Windows: miniaturas
        '/^~\$/',               // Office: archivo de propietario (~$Informe.docx)
        '/^\.~lock\..*#$/',     // LibreOffice: bloqueo
        '/^~lock\..*#$/',
        '/^\..*\.swp$/',
    ];
}
