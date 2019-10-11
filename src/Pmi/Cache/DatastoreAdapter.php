<?php
namespace Pmi\Cache;

use Pmi\Entities\Cache;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\PruneableInterface;

class DatastoreAdapter extends AbstractAdapter implements PruneableInterface
{
    private $marshaller;

    public function __construct()
    {
        $this->marshaller = new DefaultMarshaller();
        parent::__construct();
    }

    protected function doFetch(array $ids)
    {
        $values = [];
        foreach ($ids as $id) {
            $cache = new Cache();
            $cacheItem = $cache->fetchOneById($id);
            if ($cacheItem) {
                if ($cacheItem['expire'] instanceof \DateTime && $cacheItem['expire'] <= new \DateTime()) {
                    continue;
                }
                $values[$id] = $this->marshaller->unmarshall($cacheItem['data']);
            }
        }
        return $values;
    }

    protected function doHave($id)
    {
        return false;
    }

    protected function doClear($namespace)
    {
        return true;
    }

    protected function doDelete(array $ids)
    {
        return true;
    }

    protected function doSave(array $values, $lifetime)
    {
        $values = $this->marshaller->marshall($values, $failed);
        $expireTime = new \DateTime('+' . $lifetime . 'seconds');
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

    public function prune()
    {
        return true;
    }
}
