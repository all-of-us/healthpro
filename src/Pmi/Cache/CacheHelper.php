<?php

namespace Pmi\Cache;

use Pmi\Entities\Cache;

class CacheHelper
{

    public function get($type, $id)
    {
        if ($type === 'datastore') {
            $cache = new Cache();
            return $cache->fetchOneById($id);
        }
    }

    public function set($type, $id, $data)
    {
        if ($type === 'datastore') {
            $cache = new Cache();
            $cache->setKeyName($id);
            $cache->setData($data);
            $cache->update();
        }
    }
}
