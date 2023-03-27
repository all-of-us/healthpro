<?php

namespace App\Datastore;

use App\HttpClient;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Cloud\Datastore\DatastoreClient;

class DatastoreClientHelper
{
    protected $datastore;

    public function __construct()
    {
        // Custom http client used to set httpHandler
        $client = new HttpClient();

        // Instantiates a Datastore client
        $this->datastore = new DatastoreClient([
            'httpHandler' => new Guzzle6HttpHandler($client),
        ]);
    }

    public function fetchAll($kind, $limit = null)
    {
        $query = $this->datastore->query()->kind($kind);
        if ($limit) {
            $query->limit($limit);
        }
        return $this->datastore->runQuery($query);
    }

    public function fetchById($kind, $id)
    {
        $key = $this->datastore->key($kind, $id);
        return $this->datastore->lookup($key);
    }

    public function insert($kind, $data, $excludeIndexes)
    {
        $task = $this->datastore->entity($kind, $data, ['excludeFromIndexes' => $excludeIndexes]);
        $this->datastore->insert($task);
        return $task;
    }

    public function upsert($kind, $id, $data, $excludeIndexes)
    {
        $key = $this->datastore->key($kind, $id);
        $task = $this->datastore->entity($key, $data, ['excludeFromIndexes' => $excludeIndexes]);
        $this->datastore->upsert($task);
        return $task;
    }

    public function delete($kind, $id)
    {
        $key = $this->datastore->key($kind, $id);
        $this->datastore->delete($key);
        return true;
    }

    public function deleteBatch($keys)
    {
        $this->datastore->deleteBatch($keys);
        return true;
    }

    public function basicQuery($kind, $property, $value, $operator, $limit)
    {
        $query = $this->datastore->query()
            ->kind($kind)
            ->filter($property, $operator, $value);
        if ($limit) {
            $query->limit($limit);
        }
        return $this->datastore->runQuery($query);
    }

    public function getKeys($results)
    {
        $keys = [];
        foreach ($results as $result) {
            $keys[] = $result->key();
        }
        return $keys;
    }
}
