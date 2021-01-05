<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SiteService
{
    private $params;
    private $session;

    public function __construct(ParameterBagInterface $params, SessionInterface $session)
    {
        $this->params = $params;
        $this->session = $session;
    }

    public function isTestSite(): bool
    {
        return $this->params->has('disable_test_access') && !empty($this->params->get('disable_test_access')) && $this->session->get('siteAwardeeId') === 'TEST';
    }
}
