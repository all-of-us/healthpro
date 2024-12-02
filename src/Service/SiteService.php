<?php

namespace App\Service;

use App\Entity\Awardee;
use App\Entity\NphSite;
use App\Entity\Organization;
use App\Entity\Site;
use App\Entity\User;
use App\Form\SiteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

class SiteService
{
    public const CABOR_STATE = 'CA';
    protected $siteNameMapper = [];
    protected $nphSiteNameMapper = [];
    protected $organizationNameMapper = [];
    protected $awardeeNameMapper = [];

    private $params;
    private $requestStack;
    private $em;
    private $userService;
    private $env;
    private $tokenStorage;

    public function __construct(ParameterBagInterface $params, RequestStack $requestStack, EntityManagerInterface $em, UserService $userService, EnvironmentService $env, TokenStorageInterface $tokenStorage)
    {
        $this->params = $params;
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->userService = $userService;
        $this->env = $env;
        $this->tokenStorage = $tokenStorage;
    }

    public function isTestSite(): bool
    {
        $siteEntity = $this->requestStack->getSession()->get('siteEntity');
        return $this->params->has('disable_test_access') && !empty($this->params->get('disable_test_access')) &&
            ($siteEntity && $siteEntity->getAwardeeId() === 'TEST');
    }


    public function isDvType(): bool
    {
        $site = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'googleGroup' => $this->getSiteId(),
            'type' => 'DV'
        ]);
        return !empty($site);
    }

    public function getSiteId()
    {
        if ($site = $this->requestStack->getSession()->get('site')) {
            return $site->id;
        }
        return null;
    }


    //Super user ex: STSI
    public function getAwardeeId()
    {
        if ($awardee = $this->requestStack->getSession()->get('awardee')) {
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

    public function isBloodDonorPmSite(): bool
    {
        $site = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'googleGroup' => $this->getSiteId(),
            'type' => 'DV',
            'dvModule' => SiteType::DV_HYBRID
        ]);
        return !empty($site);
    }

    public function getSiteAwardee()
    {
        $siteEntity = $this->requestStack->getSession()->get('siteEntity');
        return $siteEntity ? $siteEntity->getOrganization() : null;
    }


    public function getSiteOrganization()
    {
        $siteEntity = $this->requestStack->getSession()->get('siteEntity');
        return $siteEntity ? $siteEntity->getOrganizationId() : null;
    }

    public function getSiteAwardeeId(): ?string
    {
        $siteEntity = $this->requestStack->getSession()->get('siteEntity');
        return $siteEntity ? $siteEntity->getAwardeeId() : null;
    }

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

    public function getSiteIdWithPrefix(): ?string
    {
        if ($this->getSiteId()) {
            $prefix = \App\Security\User::SITE_PREFIX;
            if ($this->requestStack->getSession()->get('program') === User::PROGRAM_NPH) {
                $prefix = \App\Security\User::SITE_NPH_PREFIX;
            }
            return $prefix . $this->getSiteId();
        }
        return null;
    }

    public function isValidSite($email): bool
    {
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_NPH) {
            return $this->isValidNphSite($email);
        }
        return $this->isValidHpoSite($email);
    }

    public function switchSite($email): bool
    {
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_NPH) {
            return $this->switchNphSite($email);
        }
        return $this->switchHpoSite($email);
    }

    public function switchHpoSite($email): bool
    {
        $user = $this->userService->getUser();
        if ($user && $user->belongsToSite($email)) {
            $this->requestStack->getSession()->set('site', $user->getSite($email));
            $this->requestStack->getSession()->remove('awardee');
            $this->setNewRoles($user);
            $this->saveSiteMetaDataInSession();
            return true;
        } elseif ($user && $user->belongsToAwardee($email)) {
            $this->requestStack->getSession()->set('awardee', $user->getAwardee($email));
            $this->requestStack->getSession()->remove('site');
            $this->setNewRoles($user);
            // Clears previously set site meta data
            $this->saveSiteMetaDataInSession();
            return true;
        }
        return false;
    }

    public function switchNphSite($email): bool
    {
        $user = $this->userService->getUser();
        if ($user && $user->belongsToSite($email, 'nphSites')) {
            $this->requestStack->getSession()->set('site', $user->getSite($email, 'nphSites'));
            $this->requestStack->getSession()->remove('awardee');
            $this->setNewRoles($user);
            $this->saveSiteMetaDataInSession();
            return true;
        }
        return false;
    }

    public function saveSiteMetaDataInSession()
    {
        $site = $this->getSiteEntity();
        if (!empty($site)) {
            $this->requestStack->getSession()->set('siteEntity', $site);
            $this->requestStack->getSession()->set('siteOrganizationDisplayName', $this->getOrganizationDisplayName($site->getOrganizationId()));
            $this->requestStack->getSession()->set('siteAwardeeDisplayName', $this->getAwardeeDisplayName($site->getAwardeeId()));
            $this->requestStack->getSession()->set('siteType', $this->getSiteType());
            $this->requestStack->getSession()->set('orderType', $this->getOrderType());
        } else {
            $this->requestStack->getSession()->remove('siteEntity');
            $this->requestStack->getSession()->remove('siteOrganizationDisplayName');
            $this->requestStack->getSession()->remove('siteAwardeeDisplayName');
            $this->requestStack->getSession()->remove('siteType');
            $this->requestStack->getSession()->remove('orderType');
        }
    }

    public function getSiteEntity()
    {
        $googleGroup = $this->getSiteId();
        if (!$googleGroup) {
            return null;
        }
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_NPH) {
            $site = $this->em->getRepository(NphSite::class)->findBy(['deleted' => 0, 'googleGroup' => $googleGroup]);
        } else {
            $site = $this->em->getRepository(Site::class)->findBy(['deleted' => 0, 'googleGroup' => $googleGroup]);
        }
        return !empty($site) ? $site[0] : null;
    }

    public function getOrderType()
    {
        if ($this->isDVType() && !$this->isDiversionPouchSite()) {
            return 'dv';
        }
        return 'hpo';
    }

    public function getSiteType()
    {
        return $this->isDVType() ? 'dv' : 'hpo';
    }

    public function displayCaborConsent(): bool
    {
        $siteEntity = $this->requestStack->getSession()->get('siteEntity');
        return $siteEntity && $siteEntity->getState() === self::CABOR_STATE ? true : false;
    }

    public function getSiteWithPrefix($siteId): string
    {
        return \App\Security\User::SITE_PREFIX . $siteId;
    }

    public function canSwitchProgram(): bool
    {
        $user = $this->userService->getUser();
        return $user && (($user->getNphSites() && $user->getSites()) || $this->hasCrossProgramRoles());
    }

    public function autoSwitchSite(): bool
    {
        $program = $this->requestStack->getSession()->get('program');
        $user = $this->userService->getUser();
        $sites = $program === User::PROGRAM_HPO ? $user->getSites() : $user->getNphSites();
        $autoSwitch = false;
        if ($program === User::PROGRAM_HPO) {
            if (count($sites) === 1 && empty($user->getAwardees()) && $this->isValidSite($sites[0]->email)) {
                $this->switchSite($sites[0]->email);
                $autoSwitch = true;
            } elseif (count($user->getAwardees()) === 1 && empty($sites)) {
                $this->switchSite($user->getAwardees()[0]->email);
                $autoSwitch = true;
            }
        } else {
            if (count($sites) === 1 && $this->isValidSite($sites[0]->email)) {
                $this->switchSite($sites[0]->email);
                $autoSwitch = true;
            }
        }
        if (count($sites) === 0) {
            $autoSwitch = true;
        }
        return $autoSwitch;
    }

    public function getNphSiteDisplayName(string $siteSuffix, bool $defaultToSiteSuffix = true): ?string
    {
        $siteName = $defaultToSiteSuffix ? $siteSuffix : null;
        if (!empty($siteSuffix)) {
            if (array_key_exists($siteSuffix, $this->siteNameMapper)) {
                $siteName = $this->nphSiteNameMapper[$siteSuffix];
            } else {
                $site = $this->em->getRepository(NphSite::class)->findOneBy([
                    'deleted' => 0,
                    'googleGroup' => $siteSuffix
                ]);
                if (!empty($site)) {
                    $siteName = $this->nphSiteNameMapper[$siteSuffix] = $site->getName();
                }
            }
        }
        return $siteName;
    }

    public function resetUserRoles(): void
    {
        $this->requestStack->getSession()->remove('site');
        $this->requestStack->getSession()->remove('awardee');
        $user = $this->userService->getUser();
        $token = new PostAuthenticationGuardToken($user, 'main', $user->getAllRoles());
        $this->tokenStorage->setToken($token);
    }

    public function isActiveSite($siteId = null): bool
    {
        $siteId = $siteId ?: $this->getSiteId();
        return $this->em->getRepository(Site::class)->getActiveSiteCount($siteId) > 0;
    }

    protected function setNewRoles($user)
    {
        $userRoles = $this->userService->getRoles($user->getAllRoles(), $this->requestStack->getSession()->get('site'), $this->requestStack->getSession()->get('awardee'));
        if ($user->getAllRoles() != $userRoles) {
            $token = new PostAuthenticationGuardToken($user, 'main', $userRoles);
            $this->tokenStorage->setToken($token);
        }
    }

    private function isValidHpoSite($email): bool
    {
        $user = $this->userService->getUser();
        if (!$user || !$user->belongsToSite($email)) {
            return false;
        }
        if (!$this->env->isLocal()) {
            $siteGroup = $user->getSite($email);
            $site = $this->em->getRepository(Site::class)->findOneBy([
                'deleted' => 0,
                'googleGroup' => $siteGroup->id,
            ]);
            if (!$site) {
                return false;
            }
            if (empty($site->getMayolinkAccount())) {
                return false;
            }
        }
        return true;
    }

    private function isValidNphSite($email): bool
    {
        $user = $this->userService->getUser();
        if (!$user || !$user->belongsToSite($email, 'nphSites')) {
            return false;
        }
        if ($this->env->isStable() || $this->env->isProd()) {
            $siteGroup = $user->getSite($email, 'nphSites');
            $site = $this->em->getRepository(NphSite::class)->findOneBy([
                'deleted' => 0,
                'googleGroup' => $siteGroup->id,
            ]);
            if (!$site || empty($site->getMayolinkAccount())) {
                return false;
            }
        }
        return true;
    }

    private function hasCrossProgramRoles(): bool
    {
        $user = $this->userService->getUser();
        return $user && (
            (in_array('ROLE_BIOBANK', $user->getRoles()) && in_array('ROLE_NPH_BIOBANK', $user->getRoles())) ||
            (in_array('ROLE_ADMIN', $user->getRoles()) && $user->getNphSites()) ||
            (in_array('ROLE_NPH_ADMIN', $user->getRoles()) && $user->getSites()) ||
            (
                in_array('ROLE_ADMIN', $user->getRoles()) && in_array('ROLE_NPH_ADMIN', $user->getRoles())
            ) ||
            (in_array('ROLE_BIOBANK', $user->getRoles()) && $user->getNphSites()) ||
            (in_array('ROLE_NPH_BIOBANK', $user->getRoles()) && $user->getSites()) ||
            (in_array('ROLE_ADMIN', $user->getRoles()) && in_array('ROLE_NPH_BIOBANK', $user->getRoles())) ||
            (in_array('ROLE_NPH_ADMIN', $user->getRoles()) && in_array('ROLE_BIOBANK', $user->getRoles()))
        );
    }
}
