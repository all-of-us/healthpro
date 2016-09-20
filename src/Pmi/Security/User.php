<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const SITE_PREFIX = 'hpo-site-';
    
    private $googleUser;
    private $groups;
    private $sites;
    private $bypassGroupsAuth = false;
    
    public function __construct($googleUser, array $groups, $bypassGroupsAuth = false) {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->sites = $this->computeSites();
        $this->bypassGroupsAuth = $bypassGroupsAuth;
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
            if (strpos($group->getEmail(), self::SITE_PREFIX) === 0) {
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
    
    public function getSite($email)
    {
        $site = null;
        foreach ($this->sites as $s) {
            if ($s->email === $email) {
                $site = $s;
                break;
            }
        }
        return $site;
    }
    
    public function belongsToSite($email)
    {
        $belongs = false;
        foreach ($this->sites as $site) {
            if ($site->email === $email) {
                $belongs = true;
                break;
            }
        }
        return $belongs;
    }
    
    public function getGoogleUser()
    {
        return $this->googleUser;
    }
    
    public function getRoles()
    {
        if ($this->bypassGroupsAuth) {
            return ['ROLE_USER'];
        } else {
            return count($this->groups) > 0 ? ['ROLE_USER'] : [];
        }
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
