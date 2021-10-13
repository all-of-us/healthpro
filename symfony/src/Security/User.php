<?php

namespace App\Security;

use Pmi\Service\UserService;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    const SITE_PREFIX = 'hpo-site-';
    const AWARDEE_PREFIX = 'awardee-';
    const ADMIN_GROUP = 'site-admin';
    const TWOFACTOR_GROUP = 'mfa_exception';
    const TWOFACTOR_PREFIX = 'x-site-';
    const ADMIN_DV = 'dv-admin';
    const BIOBANK_GROUP = 'biospecimen-non-pii';
    const SCRIPPS_GROUP = 'scripps-non-pii';
    const AWARDEE_SCRIPPS = 'stsi';

    const DEFAULT_TIMEZONE = 'America/New_York';

    private $googleUser;
    private $groups;
    private $sites;
    private $awardees;
    private $adminAccess;
    private $info;
    private $timezone;
    private $lastLogin;
    private $sessionInfo;
    private $adminDvAccess;
    private $biobankAccess;
    private $scrippsAccess;
    private $scrippsAwardee;

    public function __construct($googleUser, array $groups, $info = null, $timezone = null, $sessionInfo = null)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->info = $info;
        $this->timezone = is_null($timezone) && isset($info['timezone']) ? $info['timezone'] : $timezone;
        $this->sessionInfo = $sessionInfo;
        $this->sites = $this->computeSites();
        $this->awardees = $this->computeAwardees();
        $this->adminAccess = $this->computeAdminAccess();
        $this->adminDvAccess = $this->computeAdminDvAccess();
        $this->biobankAccess = $this->computeBiobankAccess();
        $this->scrippsAccess = $this->computeScrippsAccess();
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
                $sites[] = (object)[
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
                $awardees[] = (object)[
                    'email' => $group->getEmail(),
                    'name' => $group->getName(),
                    'id' => $id
                ];
                // Check for scripps awardee
                if ($id === self::AWARDEE_SCRIPPS) {
                    $this->scrippsAwardee = true;
                }
            }
        }
        return $awardees;
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

    private function computeBiobankAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::BIOBANK_GROUP . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }

    private function computeScrippsAccess()
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::SCRIPPS_GROUP . '@') === 0) {
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

    public function getAllRoles()
    {
        $roles = [];
        if (count($this->sites) > 0) {
            $roles[] = 'ROLE_USER';
        }
        if (count($this->awardees) > 0) {
            $roles[] = 'ROLE_AWARDEE';
        }
        if ($this->adminAccess) {
            $roles[] = 'ROLE_ADMIN';
        }
        if ($this->adminDvAccess) {
            $roles[] = 'ROLE_DV_ADMIN';
        }
        if ($this->biobankAccess) {
            $roles[] = 'ROLE_BIOBANK';
        }
        if ($this->scrippsAccess) {
            $roles[] = 'ROLE_SCRIPPS';
        }
        if ($this->scrippsAwardee) {
            $roles[] = 'ROLE_AWARDEE_SCRIPPS';
        }
        if (!empty($this->sessionInfo['managegroups'])) {
            $roles[] = 'ROLE_MANAGE_USERS';
        }
        return $roles;
    }

    public function getRoles()
    {
        return UserService::getRoles($this->getAllRoles(), $this->sessionInfo['site'], $this->sessionInfo['awardee']);
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

    public function getTimezone($useDefault = true)
    {
        if (!$this->timezone) {
            return $useDefault ? self::DEFAULT_TIMEZONE : null;
        }
        return $this->timezone;
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    public function getLastlogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin)
    {
        $this->lastLogin = $lastLogin;
    }

    public function getId()
    {
        if (isset($this->info['id'])) {
            return $this->info['id'];
        } else {
            return false;
        }
    }

    public function getSiteFromId($siteId)
    {
        $site = null;
        foreach ($this->sites as $s) {
            if ($s->id === $siteId) {
                $site = $s;
                break;
            }
        }
        return $site;
    }
}
