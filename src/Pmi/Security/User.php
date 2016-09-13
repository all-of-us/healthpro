<?php
namespace Pmi\Security;

use google\appengine\api\users\User as GoogleUser;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $googleUser;
    private $groups;
    private $sites;
    
    public function __construct(GoogleUser $googleUser, array $groups) {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->sites = $this->computeSites();
    }
    
    public function getGroups()
    {
        return $this->groups;
    }
    
    private function computeSites()
    {
        $sites = [];
        // site membership is determined by the user's groups
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), 'hpo-site-') === 0) {
                $sites[] = (object) [
                    'email' => $group->getEmail(),
                    'name' => $group->getName()
                ];
            }
        }
        return $sites;
    }
    
    public function getSites()
    {
        return $this->sites;
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
