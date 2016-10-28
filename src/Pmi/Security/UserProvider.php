<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

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
            $groups = $this->app['pmi.drc.appsclient'] ? $this->app['pmi.drc.appsclient']->getGroups($googleUser->getEmail()) : [];
            $this->app['session']->set('googlegroups', $groups);
        }
        $userInfo = $this->getUserInfo($googleUser);
        return new User($googleUser, $groups, $userInfo);
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
            ];
        }
        $userInfo = $this->app['em']->getRepository('users')->fetchOneBy([
            'email' => $googleUser->getEmail()
        ]);
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
