<?php

namespace App\Datastore;

use App\HttpClient;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\EntityInterface;
use Google\Cloud\Datastore\Key;

class DatastoreClientHelper
{
    protected DatastoreClient $datastore;

    public function __construct()
    {
        // Custom http client used to set httpHandler
        $client = new HttpClient();

        // Instantiates a Datastore client
        $this->datastore = new DatastoreClient([
            'httpHandler' => new Guzzle6HttpHandler($client),
        ]);
    }

    /**
     * @return iterable<int, EntityInterface>
     */
    public function fetchAll(string $kind, ?int $limit = null): iterable
    {
        $query = $this->datastore->query()->kind($kind);
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $this->datastore->runQuery($query);
    }

    public function fetchById(string $kind, int|string $id): ?EntityInterface
    {
        $key = $this->datastore->key($kind, $id);
        return $this->datastore->lookup($key);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $excludeIndexes
     */
    public function insert(string $kind, array $data, array $excludeIndexes): EntityInterface
    {
        $task = $this->datastore->entity($kind, $data, ['excludeFromIndexes' => $excludeIndexes]);
        $this->datastore->insert($task);
        return $task;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $excludeIndexes
     */
    public function upsert(string $kind, int|string $id, array $data, array $excludeIndexes): EntityInterface
    {
        $key = $this->datastore->key($kind, $id);
        $task = $this->datastore->entity($key, $data, ['excludeFromIndexes' => $excludeIndexes]);
        $this->datastore->upsert($task);
        return $task;
    }

    public function delete(string $kind, int|string $id): bool
    {
        $key = $this->datastore->key($kind, $id);
        $this->datastore->delete($key);
        return true;
    }

    /**
     * @param list<Key|null> $keys
     */
    public function deleteBatch(array $keys): bool
    {
        $this->datastore->deleteBatch($keys);
        return true;
    }

    /**
     * @return iterable<int, EntityInterface>
     */
    public function basicQuery(string $kind, string $property, mixed $value, string $operator, ?int $limit): iterable
    {
        $query = $this->datastore->query()
            ->kind($kind)
            ->filter($property, $operator, $value);
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $this->datastore->runQuery($query);
    }

    /**
     * @param iterable<int, EntityInterface> $results
     *
     * @return list<Key|null>
     */
    public function getKeys(iterable $results): array
    {
        $keys = [];
        foreach ($results as $result) {
            $keys[] = $result->key();
        }
        return $keys;
    }
}
