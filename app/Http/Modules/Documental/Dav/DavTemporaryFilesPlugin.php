<?php

namespace App\Http\Modules\Documental\Dav;

use Sabre\DAV\Locks\Backend\BackendInterface as LocksBackend;
use Sabre\DAV\TemporaryFileFilterPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

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

    /**
     * Windows ajusta propiedades (fechas, atributos) del archivo de propietario de Office (~$Libro.xlsx) con PROPPATCH.
     * El filtro original no lo atiende y el plugin de bloqueos lo rechazaba con 423: se acepta sin guardar nada.
     */
    public function __construct(?string $dataDir = null, protected ?LocksBackend $locks = null)
    {
        parent::__construct($dataDir);
    }

    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        // Al borrar el archivo de propietario de Office (~$Libro.xlsx) se liberan sus bloqueos: el filtro original
        // no lo hace y la siguiente vez que se abre el libro Windows recibía 423 (archivo bloqueado)
        if ($this->locks && $request->getMethod() === 'DELETE' && $this->isTempFile($request->getPath())) {
            foreach ($this->locks->getLocks($request->getPath(), false) as $lock) {
                $this->locks->unlock($request->getPath(), $lock);
            }
        }

        if ($request->getMethod() === 'PROPPATCH' && $this->isTempFile($request->getPath())) {
            $properties = [];
            try {
                $properties = array_keys($this->server->xml->expect('{DAV:}propertyupdate', $request->getBodyAsString())->properties);
            } catch (\Throwable) {
            }

            $response->setStatus(207);
            $response->setHeader('Content-Type', 'application/xml; charset=utf-8');
            $response->setBody($this->server->generateMultiStatus([
                ['href' => $request->getPath(), 200 => array_fill_keys($properties, null)],
            ]));

            return false;
        }

        return parent::beforeMethod($request, $response);
    }
}
