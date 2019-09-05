<?php
namespace Pmi\Service;

use DateTime;
use Pmi\Entities\Cache;

class CacheService
{
    public function deleteKeys()
    {
        $now = new DateTime();
        $cache = new Cache();
        $results = $cache->getBatch('expire', $now, '<');
        $cache->deleteBatch($results);
    }
}
