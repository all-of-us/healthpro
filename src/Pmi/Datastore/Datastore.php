<?php
namespace Pmi\Datastore;

use GDS\Store as Repository;
use GDS\Gateway\ProtoBuf;

class Datastore
{
    public function insert($type, $data, $keyName = null, $keyId = null)
    {
        $repository = new Repository($type);
        $entity = $repository->createEntity($data);
        if (!is_null($keyName)) {
            $entity->setKeyName($keyName);
        }
        if (!is_null($keyId)) {
            $entity->setKeyId($keyId);
        }
        $repository->upsert($entity);
        return $entity->getKeyId();
    }

    public function fetchAll($type)
    {
        $repository = new Repository($type);
        return $repository->fetchAll();
    }

    public function fetchAllBySql($type, $sql, $params)
    {
        $repository = new Repository($type);
        return $repository->fetchAll($sql, $params);
    }

    public function fetchOneBySql($type, $sql, $params)
    {
        $repository = new Repository($type);
        return $repository->fetchOne($sql, $params);
    }

    public function fetchOneByKey($type, $key)
    {
        $repository = new Repository($type);
        return $repository->fetchById($key);
    }

    public function fetchOneByName($type, $name)
    {
        $repository = new Repository($type);
        return $repository->fetchByName($name);
    }
    
    /**
     * Applies a callback to each entity on each page returned by the query.
     * For performance, the entity passed to the callback will be a GDS\Entity.
     */
    public function walkEntities(callable $callback, $type, $sql, $params)
    {
        $repository = new Repository($type);
        $repository->query($sql, $params);
        while ($page = $repository->fetchPage(ProtoBuf::BATCH_SIZE)) {
            foreach ($page as $entity) {
                $callback($entity);
            }
        }
    }
    
    /** Counts all queried entities, using paging to include them all. */
    public function countEntities($type, $sql, $params, $limit = 0)
    {
        $count = 0;
        $repository = new Repository($type);
        $repository->query($sql, $params);
        while ($page = $repository->fetchPage(ProtoBuf::BATCH_SIZE)) {
            $count += count($page);
            if ($limit && $count >= $limit) {
                return $limit;
            }
        }
        return $count;
    }
}
