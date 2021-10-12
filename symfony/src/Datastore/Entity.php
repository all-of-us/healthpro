<?php

namespace App\Datastore;

abstract class Entity
{
    protected $data = [];

    protected $id;

    protected $excludeIndexes = [];

    protected $writeLimit = 500;

    abstract static protected function getKind();

    public static function fetchBy()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->fetchAll(static::getKind());
    }

    public static function fetchOneById($id)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->fetchById(static::getKind(), $id);
    }

    public function setKeyName($id)
    {
        $this->id = $id;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function save()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->insert(static::getKind(), $this->data, $this->excludeIndexes);
    }

    public function update()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->upsert(static::getKind(), $this->id, $this->data, $this->excludeIndexes);
    }

    public function delete()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->delete(static::getKind(), $this->id);
    }

    public function getBatch($property = null, $value = null, $operator = null, $limit = null)
    {
        $datastoreClient = new DatastoreClientHelper();
        if ($property === null) {
            $results = $datastoreClient->fetchAll(static::getKind(), $limit);
        } else {
            $results = $datastoreClient->basicQuery(static::getKind(), $property, $value, $operator, $limit);
        }

        return $results;
    }

    public function deleteBatch($results)
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
}
