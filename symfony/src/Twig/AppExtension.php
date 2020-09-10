<?php

namespace App\Twig;

use App\Service\TimezoneService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $router;
    private $requestStack;

    public function __construct(RouterInterface $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('path_exists', [$this, 'checkPath']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('slugify', [$this, 'slugify']),
            new TwigFunction('timezone_display', [$this, 'timezoneDisplay'])
        ];
    }

    public function checkPath($name)
    {
        return !is_null($this->router->getRouteCollection()->get($name));
    }

    public function asset($asset)
    {
        $basePath = $this->requestStack->getCurrentRequest()->getBasePath();
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

    public function timezoneDisplay(?string $timezone): string
    {
        $tsService = new TimezoneService();
        return $tsService->getTimezoneDisplay($timezone);
    }
}
