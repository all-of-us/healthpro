<?php
namespace Pmi\Security;

use google\appengine\api\users\User as GoogleUser;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $googleUser;
    
    public function __construct(GoogleUser $googleUser) {
        $this->googleUser = $googleUser;
    }
    
    public function getRoles()
    {
        return ['ROLE_USER'];
    }
    
    public function getPassword()
    {
        return null;
    }
    
    public function getSalt()
    {
        return null;
    }
    
    public function getUsername()
    {
        return $this->googleUser->getEmail();
    }
    
    public function eraseCredentials()
    {
        // we don't actually store any credentials
    }
}
