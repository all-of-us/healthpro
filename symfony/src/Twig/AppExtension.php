<?php

namespace App\Twig;

use App\Entity\Awardee;
use App\Entity\Organization;
use App\Service\TimezoneService;
use Pmi\Drc\CodeBook;
use Psr\Container\ContainerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private $container;

    private $doctrine;

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine)
    {
        $this->container = $container;
        $this->doctrine = $doctrine;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('path_exists', [$this, 'checkPath']),
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('slugify', [$this, 'slugify']),
            new TwigFunction('timezone_display', [$this, 'timezoneDisplay']),
            new TwigFunction('codebook_display', [$this, 'getCodeBookDisplay']),
            new TwigFunction('organization_display', [$this, 'getAwardeeDisplay']),
            new TwigFunction('awardee_display', [$this, 'getAwardeeDisplay'])
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

    public function timezoneDisplay(?string $timezone): string
    {
        $tsService = new TimezoneService();
        return $tsService->getTimezoneDisplay($timezone);
    }

    public function getCodeBookDisplay(string $code): string
    {
        return CodeBook::display($code);
    }

    public function getAwardeeDisplay(string $awardee): string
    {
        $repository = $this->doctrine->getRepository(Awardee::class);
        $record = $repository->find($awardee);
        if ($record) {
            return $record->getName();
        }
        return $awardee;
    }

    public function getOrganizationDisplay(string $organization): string
    {
        $repository = $this->doctrine->getRepository(Organization::class);
        $record = $repository->find($organization);
        if ($record) {
            return $record->getName();
        }
        return $organization;
    }
}
