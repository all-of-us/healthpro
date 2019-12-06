<?php
namespace Pmi\Datastore;

abstract class Entity
{
    protected $data = [];

    protected $id;

    protected $excludeIndexes = [];

    protected $limit = null;

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

    public function getBatch($property = null, $value = null, $operator = null)
    {
        $datastoreClient = new DatastoreClientHelper();
        if ($property === null) {
            $results = $datastoreClient->fetchAll(static::getKind());
        } else {
            $results = $datastoreClient->basicQuery(static::getKind(), $property, $value, $operator, $this->limit);
        }

        return $results;
    }

    public function deleteBatch($results)
    {
        $datastoreClient = new DatastoreClientHelper();
        $keys = $datastoreClient->getKeys($results);
        return $datastoreClient->deleteBatch($keys);
    }
}
