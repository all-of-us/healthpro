<?php

namespace App\Security;

use App\Service\EnvironmentService;
use App\Service\GoogleGroupsService;
use App\Service\MockGoogleGroupsService;
use App\Service\Ppsc\PpscApiService;
use App\Service\UserService;
use Google\Service\Directory\Group;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private $userService;
    private $requestStack;
    private $googleGroups;
    private $mockGoogleGroups;
    private $env;
    private $params;
    private $ppscApiService;

    public function __construct(
        UserService $userService,
        RequestStack $requestStack,
        GoogleGroupsService $googleGroups,
        MockGoogleGroupsService $mockGoogleGroups,
        EnvironmentService $env,
        ParameterBagInterface $params,
        PpscApiService $ppscApiService
    )
    {
        $this->userService = $userService;
        $this->requestStack = $requestStack;
        $this->googleGroups = $googleGroups;
        $this->mockGoogleGroups = $mockGoogleGroups;
        $this->env = $env;
        $this->params = $params;
        $this->ppscApiService = $ppscApiService;
    }

    public function loadUserByUsername($username): UserInterface
    {
        if ($this->requestStack->getSession()->get('loginType') === \App\Entity\User::SALESFORCE) {
            return $this->loadSalesforceUser($username);
        }
        return $this->loadGoogleUser($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class): bool
    {
        return User::class === $class;
    }

    private function loadGoogleUser($username): UserInterface
    {
        $googleUser = $this->userService->getGoogleUser();
        if (!$googleUser || strcasecmp($googleUser->getEmail(), $username) !== 0) {
            throw new AuthenticationException("User $username is not logged in to Google!");
        }

        if ($this->requestStack->getSession()->has('googlegroups')) {
            $groups = $this->requestStack->getSession()->get('googlegroups');
        } else {
            $manageGroups = [];
            $manageGroupsNPH = [];
            if ($this->env->isLocal() && (($this->params->has('gaBypass') && $this->params->get('gaBypass')) || $this->env->values['isUnitTest'])) {
                $groups = $this->mockGoogleGroups->getGroups($googleUser->getEmail());
            } else {
                $groups = $this->googleGroups->getGroups($googleUser->getEmail());
                if ($this->params->has('feature.manageusers') && $this->params->get('feature.manageusers')) {
                    foreach ($groups as $group) {
                        if (strpos($group->getEmail(), User::SITE_PREFIX) === 0 || strpos($group->getEmail(), User::READ_ONLY_GROUP) === 0) {
                            $role = $this->googleGroups->getRole($googleUser->getEmail(), $group->getEmail());
                            if (in_array($role, ['OWNER', 'MANAGER'])) {
                                $manageGroups[] = $group->getEmail();
                            }
                        }
                        if ((preg_replace('/@.*$/', '', $group->getEmail()) !== User::NPH_ADMIN_GROUP && strpos($group->getEmail(), User::SITE_NPH_PREFIX) === 0) || strpos($group->getEmail(), User::READ_ONLY_GROUP) === 0) {
                            $role = $this->googleGroups->getRole($googleUser->getEmail(), $group->getEmail());
                            if (in_array($role, ['OWNER', 'MANAGER'])) {
                                $manageGroupsNPH[] = $group->getEmail();
                            }
                        }
                    }
                    if ($this->params->has('feature.managegrouppilotsites') && $pilotSites = $this->params->get('feature.managegrouppilotsites')) {
                        $pilotSites = explode(',', $pilotSites);
                        foreach ($manageGroups as $key => $manageGroup) {
                            if (!in_array($manageGroup, $pilotSites)) {
                                unset($manageGroups[$key]);
                            }
                        }
                    }
                }
            }
            $this->requestStack->getSession()->set('googlegroups', $groups);
            $this->requestStack->getSession()->set('managegroups', $manageGroups);
            $this->requestStack->getSession()->set('managegroupsnph', $manageGroupsNPH);
        }
        $userInfo = $this->userService->getUserInfo($googleUser);
        $sessionInfo = [
            'site' => $this->requestStack->getSession()->get('site'),
            'awardee' => $this->requestStack->getSession()->get('awardee'),
            'program' => $this->requestStack->getSession()->get('program'),
            'managegroups' => $this->requestStack->getSession()->get('managegroups'),
            'managegroupsnph' => $this->requestStack->getSession()->get('managegroupsnph')
        ];
        return new User($googleUser, $groups, $userInfo, null, $sessionInfo);
    }

    private function loadSalesforceUser($username): UserInterface
    {
        $salesforceUser = $this->userService->getSalesforceUser();
        if (!$salesforceUser || strcasecmp($salesforceUser->getEmail(), $username) !== 0) {
            throw new AuthenticationException("User $username is not logged in!");
        }

        if ($this->requestStack->getSession()->has('googlegroups')) {
            $groups = $this->requestStack->getSession()->get('googlegroups');
        } else {
            $requestDetails = $this->ppscApiService->get('getRequestDetails', ['requestId' => $this->requestStack->getSession()->get('ppscRequestId')]);
            $responseBody = $requestDetails->getBody();
            $requestDetailsData = json_decode($responseBody->getContents(), true);
            $siteId = $requestDetailsData[0]['Site_ID__c'];
            $groups = [new Group(['email' => User::SITE_PREFIX . $siteId, 'name' => $siteId])];
            $this->requestStack->getSession()->set('googlegroups', $groups);
            $this->requestStack->getSession()->set('managegroups', []);
            $this->requestStack->getSession()->set('managegroupsnph', []);
        }
        $userInfo = $this->userService->getUserInfo($salesforceUser);
        $sessionInfo = [
            'site' => $this->requestStack->getSession()->get('site'),
            'awardee' => $this->requestStack->getSession()->get('awardee'),
            'program' => $this->requestStack->getSession()->get('program'),
            'managegroups' => $this->requestStack->getSession()->get('managegroups'),
            'managegroupsnph' => $this->requestStack->getSession()->get('managegroupsnph')
        ];
        return new User($salesforceUser, $groups, $userInfo, null, $sessionInfo);
    }
}
