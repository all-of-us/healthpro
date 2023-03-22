<?php

namespace App\Twig;

use App\Drc\CodeBook;
use App\Service\TimezoneService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $requestStack;
    private $router;
    private $params;

    public function __construct(RouterInterface $router, RequestStack $requestStack, ParameterBagInterface $params)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->params = $params;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path_exists', [$this, 'checkPath']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('slugify', [$this, 'slugify']),
            new TwigFunction('timezone_display', [$this, 'timezoneDisplay']),
            new TwigFunction('codebook_display', [$this, 'getCodeBookDisplay']),
            new TwigFunction('display_message', [$this, 'displayMessage'])
        ];
    }

    public function checkPath($name)
    {
        return !is_null($this->router->getRouteCollection()->get($name));
    }

    public function asset($asset)
    {
        $basePath = $this->requestStack->getCurrentRequest()->getBasePath();
        if (in_array($basePath, ['/web'])) {
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

    public function getCodeBookDisplay(string $code): string
    {
        return CodeBook::display($code);
    }

    public function displayMessage($name, $type = false, $options = [])
    {
        $configPrefix = 'messaging_';
        $message = $this->params->has($configPrefix . $name) ? $this->params->get($configPrefix . $name) : '';
        if (empty($message)) {
            return '';
        }
        switch ($type) {
            case 'alert':
                if (isset($options['closeButton']) && $options['closeButton']) {
                    $message .= ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                }
                return '<div class="alert alert-info">' . $message . '</div>';
            case 'tooltip':
                $tooltipText = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<span title="' . $tooltipText . '" data-toggle="tooltip" data-container="body"><i class="fa fa-info-circle" aria-hidden="true"></i></span>';
            default:
                return $message;
        }
    }
}
