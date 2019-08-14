<?php
namespace Pmi\Datastore;

abstract class Entity
{
    protected $data = [];

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

    public function setData($data)
    {
        $this->data = $data;
    }

    public function save()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->insert(static::getKind(), $this->data);
    }

    public function update($id)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->upsert(static::getKind(), $id, $this->data);
    }

    public static function delete($id)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->delete(static::getKind(), $id);
    }

    public function gc($property, $value, $operator)
    {
        $datastoreClient = new DatastoreClientHelper();
        $results = $datastoreClient->basicQuery(static::getKind(), $property, $value, $operator);
        $keys = $datastoreClient->getKeys($results);
        return $datastoreClient->deleteBatch($keys);
    }
}
