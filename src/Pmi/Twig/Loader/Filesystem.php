<?php
namespace Pmi\Twig\Loader;

class Filesystem extends \Twig_Loader_Filesystem
{
    public function getCacheKey($name)
    {
        return $name;
    }
}
