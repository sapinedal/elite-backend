<?php

namespace App\Http\Modules\Documental\Dav;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Descargas rápidas para la unidad de red:
 * - HEAD se responde con los metadatos del listado, sin abrir el archivo en Spaces.
 * - GET (con `documental.dav_accel`) devuelve un X-Accel-Redirect: PHP solo valida el permiso y registra la descarga,
 *   y nginx trae el archivo directamente de Spaces con una URL firmada, atendiendo las lecturas por partes (Range)
 *   de forma nativa. Sin esto, cada lectura parcial obliga a PHP a releer el archivo desde el inicio.
 */
class DavFastDownloadPlugin extends ServerPlugin
{
    /** Ubicación interna de nginx que hace de proxy hacia Spaces */
    public const ACCEL_LOCATION = '/_dav_download';

    protected Server $server;

    public function initialize(Server $server): void
    {
        $this->server = $server;
        // Antes que el CorePlugin (prioridad 100)
        $server->on('method:GET', [$this, 'httpGet'], 90);
    }

    public function getPluginName(): string
    {
        return 'elite-fast-download';
    }

    public function httpGet(RequestInterface $request, ResponseInterface $response)
    {
        $node = $this->server->tree->getNodeForPath($request->getPath());
        if (!$node instanceof DavFile) {
            return null;
        }

        $isHead = $request->getHeader('X-Sabre-Original-Method') === 'HEAD';
        $accel = (bool) config('documental.dav_accel');
        if (!$isHead && !$accel) {
            return null;
        }

        $response->setHeader('Content-Type', $node->getContentType());
        $response->setHeader('ETag', $node->getETag());
        $response->setHeader('Accept-Ranges', 'bytes');
        if ($node->getLastModified()) {
            $response->setHeader('Last-Modified', HTTP\toDate(new \DateTime('@' . $node->getLastModified())));
        }

        if ($isHead) {
            $response->setHeader('Content-Length', (string) $node->getSize());
            $response->setStatus(200);

            return false;
        }

        $node->logDownload();
        $response->setHeader('X-Accel-Redirect', self::ACCEL_LOCATION);
        $response->setHeader('X-Dav-Download-Url', $node->temporaryUrl());
        $response->setHeader('X-Dav-Etag', $node->getETag());
        $response->setHeader('X-Accel-Buffering', 'no');
        $response->setStatus(200);
        $response->setBody('');

        return false;
    }
}
