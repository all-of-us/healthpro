<?php

namespace App\Service;

use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SiteService
{
    private $params;
    private $session;
    private $em;

    public function __construct(ParameterBagInterface $params, SessionInterface $session, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->session = $session;
        $this->em = $em;
    }

    public function isTestSite(): bool
    {
        return $this->params->has('disable_test_access') && !empty($this->params->get('disable_test_access')) && $this->session->get('siteAwardeeId') === 'TEST';
    }

    public function getSiteId()
    {
        if ($site = $this->session->get('site')) {
            return $site->id;
        } else {
            return null;
        }
    }

    public function isDiversionPouchSite()
    {
        if (!$this->params->has('diversion_pouch_site')) {
            return false;
        }
        $site = $this->em->getRepository(Site::class)->fetchBy([
            'deleted' => 0,
            'google_group' => $this->getSiteId(),
            'site_type' => $this->params->get('diversion_pouch_site')
        ]);
        return !empty($site);
    }
}
