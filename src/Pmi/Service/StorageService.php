<?php

namespace Pmi\Service;

use Google\Cloud\Storage\StorageClient;

use Pmi\Audit\Log;

/**
 * Storage Service
 */
class StorageService
{
    protected $client;

    public function __construct($gcsOptions)
    {
        if (!isset($gcsOptions['projectId']) || is_null($gcsOptions['projectId'])) {
            $gcsOptions['projectId'] = getenv('GOOGLE_CLOUD_PROJECT');
        }
        $this->client = new StorageClient($gcsOptions);
    }

    public function getBuckets($prefix = null)
    {
        return $this->client->buckets([
            'prefix' => $prefix
        ]);
    }

    public function getObjects($bucketName)
    {
        $bucket = $this->client->bucket($bucketName);
        return $bucket->objects();
    }

    public function getObject($bucketName, $objectPath)
    {
        $bucket = $this->client->bucket($bucketName);
        return $bucket->object($objectPath);
    }
}
