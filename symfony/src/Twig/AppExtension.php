<?php

namespace App\Twig;

use Psr\Container\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('path_exists', [$this, 'checkPath']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('slugify', [$this, 'slugify'])
        ];
    }

    public function checkPath($name)
    {
        return !is_null($this->container->get('router')->getRouteCollection()->get($name));
    }

    public function asset($asset)
    {
        $basePath = $this->container->get('request_stack')->getCurrentRequest()->getBasepath();
        if (in_array($basePath, ['/web', '/s'])) {
            // The combination of GAE's routing handlers and the Symfony Request object
            // base path logic results in an incorrect basepath for requests that start
            // with /web because the prefix is the same as the web root's directory name.
            // To account for this, we clear the basePath if it is "/web"
            $basePath = '';
        }
        $basePath .= '/assets/';
        return $basePath . ltrim($asset, '/');
    }

    public function slugify($text)
    {
        $output = trim(strtolower($text));
        $output = preg_replace('/[^a-z0-9]/', '-', $output);
        return $output;
    }
}
