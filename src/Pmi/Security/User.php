<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const SITE_PREFIX = 'hpo-site-';
    const DASHBOARD_GROUP = 'admin-dashboard';
    const ADMIN_GROUP = 'site-admin';
    const TWOFACTOR_GROUP = 'mfa_exception';
    const TWOFACTOR_PREFIX = 'x-site-';
    
    private $googleUser;
    private $groups;
    private $sites;
    private $dashboardAccess;
    private $siteAdminAccess;
    private $info;
    
    public function __construct($googleUser, array $groups, $info = null)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->info = $info;
        $this->sites = $this->computeSites();
        $this->dashboardAccess = $this->computeDashboardAccess();
        $this->siteAdminAccess = $this->computeSiteAdminAccess();
    }
    
    public function getGroups()
    {
        return $this->groups;
    }
    
    public function getInfo()
    {
        return $this->info;
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

    private function computeSiteAdminAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::ADMIN_GROUP . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }


    
    public function hasTwoFactorAuth()
    {
        // Google doesn't expose the user's current 2FA setting via API so
        // we infer it by checking whether they are in a 2FA exception group
        $twoFactorAuth = true;
        foreach ($this->groups as $group) {
            $email = $group->getEmail();
            if (strpos($email, self::TWOFACTOR_GROUP . '@') === 0) {
                $twoFactorAuth = false;
            } elseif (strpos($email, self::TWOFACTOR_PREFIX) === 0) {
                $twoFactorAuth = false;
            }
        }
        return $twoFactorAuth;
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
        $roles = [];
        if (count($this->sites) > 0) {
            $roles[] = 'ROLE_USER';
        }
        if ($this->dashboardAccess) {
            $roles[] = 'ROLE_DASHBOARD';
        }
        if ($this->siteAdminAccess) {
            $roles[] = 'ROLE_SITE_ADMIN';
        }
        return $roles;
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

    public function getId()
    {
        if (isset($this->info['id'])) {
            return $this->info['id'];
        } else {
            return false;
        }
    }
}
