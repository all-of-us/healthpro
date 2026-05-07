<?php

namespace App\Datastore;

use Google\Cloud\Datastore\EntityInterface;

abstract class Entity
{
    /** @var array<string, mixed> */
    protected array $data = [];

    protected int|string|null $id = null;

    /** @var list<string> */
    protected array $excludeIndexes = [];

    protected int $writeLimit = 500;

    /** @return iterable<int, EntityInterface> */
    public static function fetchBy(): iterable
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->fetchAll(static::getKind());
    }

    public static function fetchOneById(int|string $id): ?EntityInterface
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->fetchById(static::getKind(), $id);
    }

    public function setKeyName(int|string $id): void
    {
        $this->id = $id;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function save(): EntityInterface
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->insert(static::getKind(), $this->data, $this->excludeIndexes);
    }

    public function update(): EntityInterface
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->upsert(static::getKind(), $this->id, $this->data, $this->excludeIndexes);
    }

    public function delete(): bool
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->delete(static::getKind(), $this->id);
    }

    /** @return iterable<int, EntityInterface> */
    public function getBatch(?string $property = null, mixed $value = null, ?string $operator = null, ?int $limit = null): iterable
    {
        $datastoreClient = new DatastoreClientHelper();
        if ($property === null) {
            $results = $datastoreClient->fetchAll(static::getKind(), $limit);
        } else {
            $results = $datastoreClient->basicQuery(static::getKind(), $property, $value, $operator, $limit);
        }

        return $results;
    }

    /** @param iterable<int, EntityInterface> $results */
    public function deleteBatch(iterable $results): bool
    {
        $datastoreClient = new DatastoreClientHelper();
        $keys = $datastoreClient->getKeys($results);
        $count = ceil(count($keys) / $this->writeLimit);
        for ($i = 0; $i < $count; $i++) {
            $offset = $i * $this->writeLimit;
            $datastoreClient->deleteBatch(array_slice($keys, $offset, $this->writeLimit));
        }
        return true;
    }

    abstract protected static function getKind(): string;
}
