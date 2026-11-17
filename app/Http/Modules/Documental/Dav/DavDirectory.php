<?php

namespace App\Http\Modules\Documental\Dav;

use Sabre\DAV\Collection;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IMoveTarget;
use Sabre\DAV\INode;

/**
 * Carpeta de Gestión Documental vista desde la unidad de red. Lista solo lo que el usuario puede ver
 * y delega cada acción en DocumentalService, que aplica los mismos permisos y registros que la web.
 */
class DavDirectory extends Collection implements IMoveTarget
{
    /** @var array<string, INode>|null */
    protected ?array $children = null;

    public function __construct(
        protected DavContext $context,
        public readonly string $path = '',
    ) {
    }

    public function getName(): string
    {
        return $this->path === '' ? 'Elite' : basename($this->path);
    }

    /**
     * @return INode[]
     */
    public function getChildren(): array
    {
        if ($this->children !== null) {
            return array_values($this->children);
        }

        $listing = $this->context->run(fn () => $this->context->service->browse($this->path, $this->context->user(), ip: $this->context->ip));

        $children = [];
        foreach ($listing['folders'] as $folder) {
            $children[$folder['name']] = new DavDirectory($this->context, $folder['path']);
        }
        foreach ($listing['files'] as $file) {
            $children[$file['name']] = new DavFile($this->context, $file['path'], (int) $file['size'], $file['last_modified'] ? strtotime($file['last_modified']) : null);
        }
        $this->children = $children;

        return array_values($children);
    }

    public function getChild($name): INode
    {
        $this->getChildren();
        if (!isset($this->children[$name])) {
            throw new NotFound("No existe {$name}");
        }

        return $this->children[$name];
    }

    public function childExists($name): bool
    {
        $this->getChildren();

        return isset($this->children[$name]);
    }

    public function createFile($name, $data = null)
    {
        $this->context->run(function () use ($name, $data) {
            [$stream, $size] = DavFile::bufferBody($data);
            try {
                $this->context->service->writeFileStream(
                    $this->context->service->joinPath($this->path, $name),
                    $stream, $size, $this->context->user(), $this->context->ip, DavContext::SOURCE
                );
            } finally {
                fclose($stream);
            }
        });
        $this->children = null;

        return null;
    }

    public function createDirectory($name): void
    {
        $this->context->run(fn () => $this->context->service->createFolder($this->path, $name, $this->context->user(), $this->context->ip));
        $this->children = null;
    }

    public function delete(): void
    {
        $this->context->run(fn () => $this->context->service->deleteItem($this->path, 'folder', $this->context->user(), $this->context->ip));
    }

    public function setName($name): void
    {
        $this->context->run(fn () => $this->context->service->renameItem($this->path, $name, 'folder', $this->context->user(), $this->context->ip));
    }

    /**
     * Mover entre carpetas sin copiar y borrar elemento por elemento a través de WebDAV.
     */
    public function moveInto($targetName, $sourcePath, INode $sourceNode): bool
    {
        if (!$sourceNode instanceof DavDirectory && !$sourceNode instanceof DavFile) {
            return false;
        }

        $type = $sourceNode instanceof DavDirectory ? 'folder' : 'file';
        $this->context->run(fn () => $this->context->service->moveItem(
            $sourceNode->path,
            $this->context->service->joinPath($this->path, $targetName),
            $type,
            $this->context->user(),
            $this->context->ip,
            DavContext::SOURCE
        ));
        $this->children = null;

        return true;
    }
}
