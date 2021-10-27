<?php

namespace App\Service;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class GcsBucketService
{
    protected $storageClient;
    protected $config = [];

    public function __construct(EnvironmentService $environment, KernelInterface $appKernel, ParameterBagInterface $params)
    {
        $this->storageClient = new StorageClient();
        $basePath = $appKernel->getProjectDir();
        // Note that when installed in ./symfony, the development credentials are a level down
        if ($environment->isLocal() && file_exists($basePath . '/../dev_config/rdr_key.json')) {
            $this->config['key_file'] = $basePath . '/../dev_config/rdr_key.json';
            $this->storageClient = new StorageClient([
                'keyFilePath' => $this->config['key_file']
            ]);
        }
        if ($params->has('rdr_auth_json')) {
            $this->config['rdr_auth_json'] = $params->get('rdr_auth_json');
            $this->storageClient = new StorageClient([
                'keyFile' => $this->config['rdr_auth_json']
            ]);
        }
    }

    public function getObjectFromPath(string $bucket, string $path): StorageObject
    {
        $bucket = $this->storageClient->bucket($bucket);
        $object = $bucket->object($path);
        return $object;
    }
}
