<?php

namespace Pmi\Cache;

use Pmi\Datastore\DatastoreClientHelper;

class CacheHelper
{

    public function get($type, $id, $kind = '')
    {
        if ($type === 'memcache') {
            $memcache = new \Memcache();
            return $memcache->get($id);
        } else {
            $datastoreClient = new DatastoreClientHelper();
            return $datastoreClient->fetchById($kind, $id);
        }
    }

    public function set($type, $id, $data, $cacheTime, $kind = '')
    {
        if ($type === 'memcache') {
            $memcache = new \Memcache();
            $memcache->set($id, $data, 0, $cacheTime);
        } else {
            $datastoreClient = new DatastoreClientHelper();
            $datastoreClient->upsert($kind, $id, $data);
        }
    }
}
