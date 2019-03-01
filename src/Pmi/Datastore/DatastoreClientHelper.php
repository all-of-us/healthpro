<?php

namespace Pmi\Datastore;

use Google\Cloud\Datastore\DatastoreClient;

class DatastoreClientHelper
{

    protected $datastore;

    public function __construct()
    {
        # Google Cloud Platform project ID
        $projectId = getenv('GOOGLE_CLOUD_PROJECT');

        # Instantiates a client
        $this->datastore = new DatastoreClient([
            'projectId' => $projectId
        ]);
    }

    public function fetchAll($kind)
    {
        $query = $this->datastore->query()->kind($kind);
        return $this->datastore->runQuery($query);
    }

    public function fetchById($kind, $id)
    {
        $key = $this->datastore->key($kind, $id);
        return $this->datastore->lookup($key);
    }

    public function insert($kind, $data)
    {
        $task = $this->datastore->entity($kind, $data);
        $this->datastore->insert($task);
        return $task;
    }

    public function upsert($kind, $id, $data)
    {
        $key = $this->datastore->key($kind, $id);
        $task = $this->datastore->entity($key, $data, ['excludeFromIndexes' => ['data']]);
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

    public function basicQuery($kind, $property, $value, $operator)
    {
        $query = $this->datastore->query()
            ->kind($kind)
            ->filter($property, $operator, $value);
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
