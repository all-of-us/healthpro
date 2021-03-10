<?php

namespace App\Service;

use App\Entity\Awardee;
use App\Entity\Organization;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SiteService
{
    private $params;
    private $session;
    private $em;
    protected $siteNameMapper = [];
    protected $organizationNameMapper = [];
    protected $awardeeNameMapper = [];

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


    public function isDvType(): bool
    {
        return $this->session->get('siteType') === 'dv' ? true : false;
    }

    public function getSiteId()
    {
        if ($site = $this->session->get('site')) {
            return $site->id;
        }
        return null;
    }


    //Super user ex: STSI
    public function getAwardeeId()
    {
        if ($awardee = $this->session->get('awardee')) {
            return $awardee->id;
        }
        return null;
    }

    public function isDiversionPouchSite()
    {
        if (!$this->params->has('diversion_pouch_site')) {
            return false;
        }
        $site = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'googleGroup' => $this->getSiteId(),
            'siteType' => $this->params->get('diversion_pouch_site')
        ]);
        return !empty($site);
    }

    public function getSiteAwardee()
    {
        return $this->session->get('siteOrganization');
    }


    //This is equivalent to getSiteOrganizationId method in HpoApplication Class
    public function getSiteOrganization()
    {
        return $this->session->get('siteOrganizationId');
    }

    //This is equivalent to getAwardeeOrganization method in HpoApplication Class
    public function getSuperUserAwardees()
    {
        $sites = $this->getSuperUserAwardeeSites();
        if (!$sites) {
            return null;
        }
        $awardees = [];
        foreach ($sites as $site) {
            if (!empty($site->getAwardeeId())) {
                $awardees[] = $site->getAwardeeId();
            }
        }
        if (empty($awardees)) {
            return null;
        }
        return $awardees;
    }

    //This is equivalent to getAwardeeEntity method in HpoApplication Class
    public function getSuperUserAwardeeSites()
    {
        $awardee = $this->getAwardeeId();
        if (!$awardee) {
            return null;
        }
        return $this->em->getRepository(Site::class)->findBy([
            'deleted' => 0,
            'awardee' => $awardee,
        ]);
    }

    //This is equivalent to getSitesFromOrganization method in HpoApplication Class
    public function getAwardeeSites($awardee = null)
    {
        $awardee = $awardee ?? $this->getAwardeeId();
        if (!$awardee) {
            return null;
        }
        return $this->em->getRepository(Site::class)->findBy([
            'deleted' => 0,
            'status' => 1,
            'awardeeId' => $awardee,
        ]);
    }

    public function getAwardeeDisplayName($awardeeId)
    {
        $awardeeName = $awardeeId;
        if (!empty($awardeeId)) {
            if (array_key_exists($awardeeId, $this->awardeeNameMapper)) {
                $awardeeName = $this->awardeeNameMapper[$awardeeId];
            } else {
                $awardee = $this->em->getRepository(Awardee::class)->findOneBy(['id' => $awardeeId]);
                if (!empty($awardee)) {
                    $awardeeName = $this->awardeeNameMapper[$awardeeId] = $awardee->getName();
                }
            }
        }
        return $awardeeName;
    }

    public function getOrganizationDisplayName($organizationId)
    {
        $organizationName = $organizationId;
        if (!empty($organizationId)) {
            if (array_key_exists($organizationId, $this->organizationNameMapper)) {
                $organizationName = $this->organizationNameMapper[$organizationId];
            } else {
                $organization = $this->em->getRepository(Organization::class)->findOneBy(['id' => $organizationId]);
                if (!empty($organization)) {
                    $organizationName = $this->organizationNameMapper[$organizationId] = $organization->getName();
                }
            }
        }
        return $organizationName;
    }

    public function getSiteDisplayName($siteSuffix, $defaultToSiteSuffix = true)
    {
        $siteName = $defaultToSiteSuffix ? $siteSuffix : null;
        if (!empty($siteSuffix)) {
            if (array_key_exists($siteSuffix, $this->siteNameMapper)) {
                $siteName = $this->siteNameMapper[$siteSuffix];
            } else {
                $site = $this->em->getRepository(Site::class)->findOneBy([
                    'deleted' => 0,
                    'googleGroup' => $siteSuffix
                ]);
                if (!empty($site)) {
                    $siteName = $this->siteNameMapper[$siteSuffix] = $site->getName();
                }
            }
        }
        return $siteName;
    }
    
    public function getSiteIdWithPrefix()
    {
        if ($this->getSiteId()) {
            return \Pmi\Security\User::SITE_PREFIX . $this->getSiteId();
        }
        return null;
    }
}
