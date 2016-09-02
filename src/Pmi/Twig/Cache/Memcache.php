<?php
namespace Pmi\Twig\Cache;

class Memcache implements \Twig_CacheInterface
{
    public function generateKey($name, $className)
    {
        return 'twig_' . $className;
    }

    public function write($key, $content)
    {
        $content = preg_replace('/^<\?php/', '', $content);
        $content = '/* PMI_TWIG_TIMESTAMP = ' . time() . '  */' . $content;
        $memcache = new \Memcache();
        $memcache->set($key, $content);
    }

    public function load($key)
    {
        $memcache = new \Memcache();
        $result = $memcache->get($key);
        if ($result && preg_match('/PMI_TWIG_TIMESTAMP = (\d+)/', $result, $m)) {
            @eval($result);
        }
    }

    public function getTimestamp($key)
    {
        $memcache = new \Memcache();
        $result = $memcache->get($key);
        if ($result && preg_match('/PMI_TWIG_TIMESTAMP = (\d+)/', $result, $m)) {
            return $m[1];
        } else {
            return 0;
        }
    }
}
