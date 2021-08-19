<?php

namespace App\Security;

use App\Service\EnvironmentService;
use App\Service\GoogleGroupsService;
use App\Service\MockGoogleGroupsService;
use App\Service\UserService;
use Pmi\Security\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use \Google_Service_Directory_Group as Group;

class UserProvider implements UserProviderInterface
{
    private $userService;
    private $session;
    private $googleGroups;
    private $mockGoogleGroups;
    private $env;
    private $params;

    public function __construct(UserService $userService, SessionInterface $session, GoogleGroupsService $googleGroups, MockGoogleGroupsService $mockGoogleGroups, EnvironmentService $env, ParameterBagInterface $params)
    {
        $this->userService = $userService;
        $this->session = $session;
        $this->googleGroups = $googleGroups;
        $this->mockGoogleGroups = $mockGoogleGroups;
        $this->env = $env;
        $this->params = $params;
    }

    public function loadUserByUsername($username)
    {
        $googleUser = $this->userService->getGoogleUser();
        if (!$googleUser || strcasecmp($googleUser->getEmail(), $username) !== 0) {
            throw new AuthenticationException("User $username is not logged in to Google!");
        }

        if ($this->session->has('googlegroups')) {
            $groups = $this->session->get('googlegroups');
        } else {
            $manageGroups = [];
            if ($this->env->isLocal() && (($this->params->has('gaBypass') && $this->params->get('gaBypass')) || $this->env->values['isUnitTest'])) {
                $groups = $this->mockGoogleGroups->getGroups($googleUser->getEmail());
            } else {
                $groups = $this->googleGroups->getGroups($googleUser->getEmail());
                if ($this->params->has('feature.manageusers') && $this->params->get('feature.manageusers')) {
                    foreach ($groups as $group) {
                        if (strpos($group->getEmail(), User::SITE_PREFIX) === 0) {
                            $role = $this->googleGroups->getRole($googleUser->getEmail(), $group->getEmail());
                            if (in_array($role, ['OWNER', 'MANAGER'])) {
                                $manageGroups[] = $group->getEmail();
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
            $this->session->set('googlegroups', $groups);
            $this->session->set('managegroups', $manageGroups);
        }
        $userInfo = $this->userService->getUserInfo($googleUser);
        $sessionInfo = [
            'site' => $this->session->get('site'),
            'awardee' => $this->session->get('awardee'),
            'managegroups' => $this->session->get('managegroups')
        ];
        return new User($googleUser, $groups, $userInfo, null, $sessionInfo);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
