<?php
namespace Pmi\Datastore;

abstract class Entity
{
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

    public static function insertData($data)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->insert(static::getKind(), $data);
    }

    public static function upsertData($id, $data)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->upsert(static::getKind(), $id, $data);
    }

    public static function deleteData($id)
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->delete(static::getKind(), $id);
    }

    public static function gc($property, $value, $operator)
    {
        $datastoreClient = new DatastoreClientHelper();
        $results = $datastoreClient->basicQuery(static::getKind(), $property, $value, $operator);
        $keys = $datastoreClient->getKeys($results);
        return $datastoreClient->deleteBatch($keys);
    }
}
