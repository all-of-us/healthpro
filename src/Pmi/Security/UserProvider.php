<?php
namespace Pmi\Security;

use google\appengine\api\users\UserService;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
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
        $googleUser = UserService::getCurrentUser();
        if (!$googleUser || $googleUser->getEmail() !== strtolower($username)) {
            throw new \Exception("User $username is not logged in!");
        }
        
        $groups = $this->app['pmi.drc.appsclient']->getGroups($googleUser->getEmail());
        return new User($googleUser, $groups);
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
}
