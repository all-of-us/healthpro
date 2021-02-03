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

    public function getSiteId()
    {
        if ($site = $this->session->get('site')) {
            return $site->id;
        } else {
            return null;
        }
    }

    public function getAwardeeId()
    {
        if ($awardee = $this->session->get('awardee')) {
            return $awardee->id;
        } else {
            return null;
        }
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

    public function getSiteOrganization()
    {
        return $this->session->get('siteOrganizationId');
    }


    public function getSuperUserAwardees()
    {
        $sites = $this->getSiteAwardees();
        if (!$sites) {
            return null;
        } else {
            $awardees = [];
            foreach ($sites as $site) {
                if (!empty($site['awardee'])) {
                    $awardees[] = $site['awardee'];
                }
            }
            if (empty($awardees)) {
                return null;
            } else {
                return $awardees;
            }
        }
    }

    public function getSiteAwardees($awardee = null)
    {
        $awardee = $awardee ?? $this->getAwardeeId();
        if (!$awardee) {
            return null;
        }
        return $this->em->getRepository(Site::class)->findBy([
            'deleted' => 0,
            'status' => 1,
            'awardee' => $awardee,
        ]);
    }

    public function getSiteOrganizations($organization)
    {
        return $this->em->getRepository(Site::class)->findBy([
            'deleted' => 0,
            'status' => 1,
            'organization' => $organization,
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
                    $awardeeName = $this->awardeeNameMapper[$awardeeId] = $awardee['name'];
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
                    $organizationName = $this->organizationNameMapper[$organizationId] = $organization['name'];
                }
            }
        }
        return $organizationName;
    }
}
