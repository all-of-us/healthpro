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
    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var array
     */
    protected $siteNameMapper = [];
    /**
     * @var array
     */
    protected $organizationNameMapper = [];
    /**
     * @var array
     */
    protected $awardeeNameMapper = [];

    /**
     * SiteService constructor.
     * @param ParameterBagInterface $params
     * @param SessionInterface $session
     * @param EntityManagerInterface $em
     */
    public function __construct(ParameterBagInterface $params, SessionInterface $session, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->session = $session;
        $this->em = $em;
    }

    /**
     * @return bool
     */
    public function isTestSite(): bool
    {
        return $this->params->has('disable_test_access') && !empty($this->params->get('disable_test_access')) && $this->session->get('siteAwardeeId') === 'TEST';
    }

    /**
     * @return bool
     */
    public function isDvType(): bool
    {
        return $this->session->get('siteType') === 'dv' ? true : false;
    }

    /**
     * @return null
     */
    public function getSiteId()
    {
        if ($site = $this->session->get('site')) {
            return $site->id;
        }
        return null;
    }

    /**
     * @return null
     * Super user ex: STSI
     */
    public function getAwardeeId()
    {
        if ($awardee = $this->session->get('awardee')) {
            return $awardee->id;
        }
        return null;
    }

    /**
     * @return bool
     */
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

    /**
     * @return mixed
     */
    public function getSiteAwardee()
    {
        return $this->session->get('siteOrganization');
    }

    /**
     * @return mixed
     * This is equivalent to getSiteOrganizationId method in HpoApplication Class
     */
    public function getSiteOrganization()
    {
        return $this->session->get('siteOrganizationId');
    }


    /**
     * @return array|null
     * This is equivalent to getAwardeeOrganization method in HpoApplication Class
     */
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

    /**
     * @param null $awardee
     * @return null|object[]
     * This is equivalent to getAwardeeEntity method in HpoApplication Class
     */
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

    /**
     * @param null $awardee
     * @return null|object[]
     * This is equivalent to getSitesFromOrganization method in HpoApplication Class
     */
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

    /**
     * @param $awardeeId
     * @return mixed
     */
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

    /**
     * @param $organizationId
     * @return mixed
     */
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

    /**
     * @param $siteSuffix
     * @param bool $defaultToSiteSuffix
     * @return mixed|null
     */
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
}
