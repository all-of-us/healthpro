<?php
namespace Pmi\Security;

use google\appengine\api\users\User as GoogleUser;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    protected $googleUser;
    protected $groups;
    public $sites = [];
    
    public function __construct(GoogleUser $googleUser, array $groups) {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
    }
    
    public function getGroups()
    {
        return $this->groups;
    }
    
    public function getRoles()
    {
        return count($this->groups) > 0 ? ['ROLE_USER'] : [];
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
    
    public function getEmail()
    {
        return $this->googleUser->getEmail();
    }
    
    public function eraseCredentials()
    {
        // we don't actually store any credentials
    }
}
