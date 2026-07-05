<?php

namespace App\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StorageService
{
    private string $storageType;

    public function __construct(
        private FilesystemOperator $filesystem,
        private ParameterBagInterface $parameterBag,
    ) {
        $dsn = $parameterBag->get('storageDsn');
        $this->storageType = str_starts_with($dsn, 's3://') ? 's3' : 'local';
    }

    public function getFilesystem(): FilesystemOperator
    {
        return $this->filesystem;
    }

    public function isS3(): bool
    {
        return $this->storageType === 's3';
    }

    public function isLocal(): bool
    {
        return $this->storageType === 'local';
    }

    /**
     * Generate the public URL for a file path.
     * For S3: full URL to the S3 endpoint
     * For local: relative web path (e.g. /uploads/avatar/hex.jpg)
     */
    public function getPublicUrl(string $relativePath): string
    {
        if ($this->isS3()) {
            $endpoint = rtrim($this->parameterBag->get('s3Endpoint'), '/');
            $bucket = $this->parameterBag->get('s3Bucket');

            return $endpoint.'/'.$bucket.'/public/uploads/'.$relativePath;
        }

        return '/uploads/'.$relativePath;
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->filesystem->write($path, $contents, $config);
    }

    public function writeStream(string $path, $resource, array $config = []): void
    {
        $this->filesystem->writeStream($path, $resource, $config);
    }

    public function read(string $path): string
    {
        return $this->filesystem->read($path);
    }

    public function readStream(string $path)
    {
        return $this->filesystem->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->filesystem->fileExists($path)) {
            $this->filesystem->delete($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->filesystem->directoryExists($path)) {
            $this->filesystem->deleteDirectory($path);
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->filesystem->directoryExists($path);
    }

    public function createDirectory(string $path): void
    {
        if (!$this->filesystem->directoryExists($path)) {
            $this->filesystem->createDirectory($path);
        }
    }

    /**
     * @return iterable<\League\Flysystem\StorageAttributes>
     */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        return $this->filesystem->listContents($path, $deep);
    }

    public function copy(string $source, string $destination): void
    {
        $this->filesystem->copy($source, $destination);
    }

    public function move(string $source, string $destination): void
    {
        $this->filesystem->move($source, $destination);
    }

    public function getMimeType(string $path): ?string
    {
        return $this->filesystem->mimeType($path);
    }

    public function getSize(string $path): int
    {
        return $this->filesystem->fileSize($path);
    }
}
