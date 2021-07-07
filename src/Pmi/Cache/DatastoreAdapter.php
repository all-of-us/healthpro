<?php
namespace Pmi\Cache;

use Pmi\Entities\Cache;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\PruneableInterface;

class DatastoreAdapter extends AbstractAdapter implements PruneableInterface
{
    private $marshaller;

    private $limit;

    public function __construct($limit)
    {
        $this->marshaller = new DefaultMarshaller();
        $this->limit = $limit;
        parent::__construct();
    }

    protected function doFetch(array $ids)
    {
        $values = [];
        foreach ($ids as $id) {
            $cache = new Cache();
            $cacheItem = $cache->fetchOneById($id);
            if ($cacheItem) {
                if ($cacheItem['expire'] instanceof \DateTimeInterface && $cacheItem['expire'] < new \DateTime()) {
                    continue;
                }
                $values[$id] = $this->marshaller->unmarshall($cacheItem['data']);
            }
        }
        return $values;
    }

    protected function doHave($id)
    {
        return (bool) $this->doFetch([$id]);
    }

    protected function doDelete(array $ids)
    {
        foreach ($ids as $id) {
            $cache = new Cache();
            $cache->setKeyName($id);
            $cache->delete();
        }
        return true;
    }

    protected function doSave(array $values, $lifetime)
    {
        $failed = []; // Overwritten in method below (passed by reference)
        $values = $this->marshaller->marshall($values, $failed);
        if ($lifetime === 0) {
            $expireTime = null;
        } else {
            $expireTime = new \DateTime('+' . $lifetime . 'seconds');
        }
        foreach ($values as $key => $value) {
            $data = [
                'data' => $value,
                'expire' => $expireTime
            ];
            $cache = new Cache();
            $cache->setKeyName($key);
            $cache->setData($data);
            $cache->update();
        }

        return $failed;
    }

    protected function doClear($namespace)
    {
        $cache = new Cache();
        $results = $cache->getBatch(null, null, null, $this->limit);
        $cache->deleteBatch($results);

        return true;
    }

    public function prune()
    {
        $cache = new Cache();
        $results = $cache->getBatch('expire', new \DateTime(), '<', $this->limit);
        $cache->deleteBatch($results);

        return true;
    }
}
