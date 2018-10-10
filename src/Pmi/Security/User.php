<?php
namespace Pmi\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const SITE_PREFIX = 'hpo-site-';
    const AWARDEE_PREFIX = 'awardee-';
    const DASHBOARD_GROUP = 'admin-dashboard';
    const ADMIN_GROUP = 'site-admin';
    const TWOFACTOR_GROUP = 'mfa_exception';
    const TWOFACTOR_PREFIX = 'x-site-';
    const ADMIN_DV = 'dv-admin';
    
    private $googleUser;
    private $groups;
    private $sites;
    private $awardees;
    private $dashboardAccess;
    private $adminAccess;
    private $info;
    private $timezone;
    
    public function __construct($googleUser, array $groups, $info = null, $timezone = null)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->info = $info;
        $this->timezone = $timezone;
        $this->sites = $this->computeSites();
        $this->awardees = $this->computeAwardees();
        $this->dashboardAccess = $this->computeDashboardAccess();
        $this->adminAccess = $this->computeAdminAccess();
        $this->adminDvAccess = $this->computeAdminDvAccess();
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

    private function computeAwardees()
    {
        $awardees = [];
        // awardee membership is determined by the user's groups
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::AWARDEE_PREFIX) === 0) {
                $id = preg_replace('/@.*$/', '', $group->getEmail());
                $id = str_replace(self::AWARDEE_PREFIX, '', $id);
                $awardees[] = (object) [
                    'email' => $group->getEmail(),
                    'name' => $group->getName(),
                    'id' => $id
                ];
            }
        }
        return $awardees;
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

    private function computeAdminAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::ADMIN_GROUP . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }

    private function computeAdminDvAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::ADMIN_DV . '@') === 0) {
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

    public function getAwardees()
    {
        return $this->awardees;
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

    public function getAwardee($email)
    {
        $awardee = null;
        foreach ($this->awardees as $a) {
            if ($a->email === $email) {
                $awardee = $a;
                break;
            }
        }
        return $awardee;
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

    public function belongsToAwardee($email)
    {
        $belongs = false;
        foreach ($this->awardees as $awardee) {
            if ($awardee->email === $email) {
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
        if (count($this->awardees) > 0) {
            $roles[] = 'ROLE_AWARDEE';
        }
        if ($this->dashboardAccess) {
            $roles[] = 'ROLE_DASHBOARD';
        }
        if ($this->adminAccess) {
            $roles[] = 'ROLE_ADMIN';
        }
        if ($this->adminDvAccess) {
            $roles[] = 'ROLE_DV_ADMIN';
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

    public function getTimezone()
    {
        return $this->timezone;
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
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
