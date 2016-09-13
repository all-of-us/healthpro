<?php
namespace Pmi\Security;

use google\appengine\api\users\UserService;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class UserProvider implements UserProviderInterface
{
    public function loadUserByUsername($username)
    {
        $googleUser = UserService::getCurrentUser();
        if (!$googleUser || $googleUser->getEmail() !== strtolower($username)) {
            throw new \Exception("User $username is not logged in!");
        }
        
        return new User($googleUser);
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
