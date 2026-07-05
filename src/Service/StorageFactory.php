<?php

namespace App\Service;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StorageFactory
{
    public static function create(ParameterBagInterface $parameterBag): FilesystemOperator
    {
        $dsn = $parameterBag->get('storageDsn');

        if (str_starts_with($dsn, 's3://')) {
            return self::createS3Filesystem($parameterBag);
        }

        $path = preg_replace('#^local://#', '', $dsn);
        $root = $parameterBag->get('kernel.project_dir') . '/' . ltrim($path, '/');

        return self::createLocalFilesystem($root);
    }

    private static function createLocalFilesystem(string $root): FilesystemOperator
    {
        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }

        return new Filesystem(new LocalFilesystemAdapter($root));
    }

    private static function createS3Filesystem(ParameterBagInterface $parameterBag): FilesystemOperator
    {
        $client = new S3Client([
            'endpoint' => $parameterBag->get('s3Endpoint'),
            'access_key' => $parameterBag->get('s3AccessKey'),
            'secret_key' => $parameterBag->get('s3SecretKey'),
            'region' => $parameterBag->get('s3Region'),
            'version' => 'latest',
            'use_path_style_endpoint' => true,
        ]);

        return new Filesystem(new AwsS3V3Adapter(
            $client,
            $parameterBag->get('s3Bucket'),
        ));
    }
}
