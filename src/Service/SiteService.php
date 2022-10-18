<?php

namespace App\Service;

use App\Entity\Awardee;
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

    private $params;
    private $requestStack;
    private $em;
    private $userService;
    private $env;
    private $tokenStorage;
    protected $siteNameMapper = [];
    protected $organizationNameMapper = [];
    protected $awardeeNameMapper = [];

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
        return $this->params->has('disable_test_access') && !empty($this->params->get('disable_test_access')) &&
            $this->requestStack->getSession()->get('siteEntity')->getAwardeeId() === 'TEST';
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
        return $this->requestStack->getSession()->get('siteEntity')->getOrganization();
    }


    public function getSiteOrganization()
    {
        return $this->requestStack->getSession()->get('siteEntity')->getOrganizationId();
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

    public function getSiteIdWithPrefix()
    {
        if ($this->getSiteId()) {
            return \App\Security\User::SITE_PREFIX . $this->getSiteId();
        }
        return null;
    }

    public function isValidSite($email)
    {
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_HPO) {
            return $this->isValidHpoSite($email);
        }
        return $this->isValidNphSite($email);
    }

    public function isValidHpoSite($email)
    {
        $user = $this->userService->getUser();
        if (!$user || !$user->belongsToSite($email)) {
            return false;
        }
        if ($this->env->isStable() || $this->env->isProd()) {
            $siteGroup = $user->getSite($email);
            $site = $this->em->getRepository(Site::class)->findOneBy([
                'deleted' => 0,
                'googleGroup' => $siteGroup->id,
            ]);
            if (!$site) {
                return false;
            }
            if (empty($site->getMayolinkAccount()) && $site->getAwardeeId() !== 'TEST') {
                // Site is invalid if it doesn't have a MayoLINK account id, unless it is in the TEST awardee
                return false;
            }
        }
        return true;
    }

    public function isValidNphSite($email): bool
    {
        $user = $this->userService->getUser();
        if (!$user || !$user->belongsToSite($email, 'nphSites')) {
            return false;
        }
        return true;
    }

    public function switchSite($email): bool
    {
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_HPO) {
            return $this->switchHpoSite($email);
        }
        return $this->switchNphSite($email);
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
        } else {
            return false;
        }
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
        } else {
            return false;
        }
    }

    protected function setNewRoles($user)
    {
        $userRoles = $this->userService->getRoles($user->getAllRoles(), $this->requestStack->getSession()->get('site'), $this->requestStack->getSession()->get('awardee'));
        if ($user->getAllRoles() != $userRoles) {
            $token = new PostAuthenticationGuardToken($user, 'main', $userRoles);
            $this->tokenStorage->setToken($token);
        }
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
        //TODO: Use nph site class if the progam is nph
        if ($this->requestStack->getSession()->get('program') === User::PROGRAM_HPO) {
            $site = $this->em->getRepository(Site::class)->findBy(['deleted' => 0, 'googleGroup' => $googleGroup]);
            return !empty($site) ? $site[0] : null;
        }
        return null;

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
        return $this->requestStack->getSession()->get('siteEntity')->getState() === self::CABOR_STATE ? true : false;
    }

    public function getSiteWithPrefix($siteId): string
    {
        return \App\Security\User::SITE_PREFIX . $siteId;
    }

    public function canSwitchProgram(): bool
    {
        if ($this->userService->getUser()) {
            $roles = $this->userService->getUser()->getRoles();
            return in_array('ROLE_NPH_USER', $roles) && count($roles) > 1;
        }
        return false;
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
        return $autoSwitch;
    }
}
