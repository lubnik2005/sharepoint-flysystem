<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

class FlysystemSharepointAdapter implements FilesystemAdapter
{
    private SharepointConnector $connector;

    public function __construct(
        SharepointConnector $connector
    ) {
        $this->setConnector($connector);
    }

    public function getConnector(): SharepointConnector
    {
        return $this->connector;
    }

    public function setConnector(SharepointConnector $connector): FlysystemSharepointAdapter
    {
        $this->connector = $connector;
        return $this;
    }

    public function fileExists(string $path): bool
    {
        return $this->connector->getFile()->checkFileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->connector->getDirectory()->checkDirectoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $mimeType = $config->get('mimeType', 'text/plain');

        $this->connector->getFile()->writeFile($path, $contents, $mimeType);
    }

    /**
     * @param resource $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $streamContent = stream_get_contents($contents);
        if ($streamContent !== false) {
            $this->write($path, $streamContent, $config);
        } else {
            throw new \Exception('Cannot write stream (empty content)', 500);
        }
    }

    public function read(string $path): string
    {
        return $this->connector->getFile()->readFile($path);
    }

    /**
     * @return resource
     * @throws \Exception
     */
    public function readStream(string $path)
    {
        $content = $this->read($path);
        if ($content) {
            $stream = fopen('php://memory', 'r+');
            if ($stream !== false) {
                fwrite($stream, $content);
                rewind($stream);

                return $stream;
            }
        }

        throw new \Exception('Cannot read stream (empty content)', 500);
    }

    public function delete(string $path): void
    {
        $this->connector->getFile()->deleteFile($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->connector->getDirectory()->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->connector->getDirectory()->createDirectoryRecursive($path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw new \Exception('Not implemented');
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getFileAttributes($path);
    }

    /**
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function listContents(string $path, bool $deep): iterable
    {
        //return $this->connector->getDirectory()->requestDirectoryItems($path);
        $files = $this->connector->getDirectory()->requestDirectoryItems($path);
        $storages = [];
        foreach ($files as $key => $file) {
            if (isset($file['file']) && isset($file['folder'])) {
                $storages[] =  $this->getFileAttributes($path);
            }
            //$storages[] = new FlysystemStorageAttributesAdapter($file, $path);
        }
        return $storages;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $parent = explode('/', $destination);
        $fileName = array_pop($parent);

        // Create parent directories if not exists
        $parentDirectory = sprintf('/%s', ltrim(implode('/', $parent), '/'));

        $this->connector->getFile()->moveFile($source, $parentDirectory, $fileName);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $parent = explode('/', $destination);
        $fileName = array_pop($parent);

        // Create parent directories if not exists
        $parentDirectory = sprintf('/%s', ltrim(implode('/', $parent), '/'));

        $this->connector->getFile()->copyFile($source, $parentDirectory, $fileName);
    }

    private function getFileAttributes(string $path): FileAttributes
    {
        return new FileAttributes(
            $path,
            isset($file['folder']),
            isset($file['file']), 
            $this->connector->getFile()->checkFileSize($path),
            null,
            $this->connector->getFile()->checkFileLastModified($path),
            $this->connector->getFile()->checkFileMimeType($path),
            $this->connector->getFile()->requestFileMetadata($path) ?? []
        );
    }
}