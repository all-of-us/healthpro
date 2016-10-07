<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const SITE_PREFIX = 'hpo-site-';
    const DASHBOARD_GROUP = 'admin-dashboard';
    
    private $googleUser;
    private $groups;
    private $sites;
    private $dashboardAccess;
    private $bypassGroupsAuth = false;
    
    public function __construct($googleUser, array $groups, $bypassGroupsAuth = false)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->sites = $this->computeSites();
        $this->dashboardAccess = $this->computeDashboardAccess();
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
                $id = preg_replace('/@.*$/', '', $group->getEmail());
                $id = str_replace(self::SITE_PREFIX, '', $id);
                $sites[] = (object) [
                    'email' => $group->getEmail(),
                    'name' => $group->getName(),
                    'id' => $id
                ];
            }
        }
        return $sites;
    }

    private function computeDashboardAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::DASHBOARD_GROUP . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
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
            return ['ROLE_USER', 'ROLE_DASHBOARD'];
        } else {
            $roles = [];
            if (count($this->sites) > 0) {
                $roles[] = 'ROLE_USER';
            }
            if ($this->dashboardAccess) {
                $roles[] = 'ROLE_DASHBOARD';
            }
            return $roles;
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
