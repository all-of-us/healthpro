<?php

namespace App\Security;

use App\Service\EnvironmentService;
use App\Service\GoogleGroupsService;
use App\Service\MockGoogleGroupsService;
use App\Service\UserService;
use Pmi\Security\User;
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

    public function __construct(UserService $userService, SessionInterface $session, GoogleGroupsService $googleGroups, MockGoogleGroupsService $mockGoogleGroups, EnvironmentService $env)
    {
        $this->userService = $userService;
        $this->session = $session;
        $this->googleGroups = $googleGroups;
        $this->mockGoogleGroups = $mockGoogleGroups;
        $this->env = $env;
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
            if ($this->env->isLocal() && $this->userService->canMockLogin()) {
                $groups = $this->mockGoogleGroups->getGroups($googleUser->getEmail());
            } else {
                $groups = $this->googleGroups->getGroups($googleUser->getEmail());
            }
            // TODO: implement group management check
            $manageGroups = [];
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
