<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Pmi\Util;

class UserProvider implements UserProviderInterface
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function loadUserByUsername($username)
    {
        $googleUser = $this->app->getGoogleUser();
        if (!$googleUser || strcasecmp($googleUser->getEmail(), $username) !== 0) {
            throw new AuthenticationException("User $username is not logged in to Google!");
        }
        if ($this->app['session']->has('googlegroups')) {
            $groups = $this->app['session']->get('googlegroups');
        } else {
            try {
                $groups = $this->app['pmi.drc.appsclient'] ? $this->app['pmi.drc.appsclient']->getGroups($googleUser->getEmail()) : [];
                $this->app['session']->set('googlegroups', $groups);
                $manageGroups = [];
                if ($this->app->getConfig('feature.manageusers')) {
                    foreach ($groups as $group) {
                        if (strpos($group->getEmail(), User::SITE_PREFIX) === 0) {
                            $role = $this->app['pmi.drc.appsclient']->getRole($googleUser->getEmail(), $group->getEmail());
                            if (in_array($role, ['OWNER', 'MANAGER'])) {
                                $manageGroups[] = $group->getEmail();
                            }
                        }
                    }
                    if ($pilotSites = $this->app->getConfig('feature.managegrouppilotsites')) {
                        $pilotSites = explode(',', $pilotSites);
                        foreach ($manageGroups as $key => $manageGroup) {
                            if (!in_array($manageGroup, $pilotSites)) {
                                unset($manageGroups[$key]);
                            }
                        }
                    }
                }
                $this->app['session']->set('managegroups', $manageGroups);
            } catch (\Exception $e) {
                $this->app->logException($e);
                throw new AuthenticationException('Failed to retrieve group permissions');
            }
        }
        $userInfo = $this->getUserInfo($googleUser);
        $sessionInfo = [
            'site' => $this->app['session']->get('site'),
            'awardee' => $this->app['session']->get('awardee'),
            'managegroups' => $this->app['session']->get('managegroups')
        ];
        return new User($googleUser, $groups, $userInfo, null, $sessionInfo);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Pmi\Security\User';
    }

    protected function getUserInfo($googleUser)
    {
        if ($this->app['isUnitTest']) {
            return [
                'id' => 1,
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getUserId(),
                'timezone' => $googleUser->getTimezone()
            ];
        }
        $attempts = 0;
        $maxAttempts = 3;
        do {
            try {
                $userInfo = $this->app['em']->getRepository('users')->fetchOneBy([
                    'email' => $googleUser->getEmail()
                ]);
                break;
            } catch (\Exception $e) {
                if ($attempts == 2) {
                    sleep(1);
                }
                $attempts++;
            }
        } while ($attempts < $maxAttempts);
        if (!$userInfo) {
            $userInfo = [
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getUserId(),
            ];
            $id = $this->app['em']->getRepository('users')->insert($userInfo);
            $userInfo['id'] = $id;
        }
        return $userInfo;
    }
}
