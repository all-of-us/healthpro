<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    public const SITE_PREFIX = 'hpo-site-';
    public const SITE_NPH_PREFIX = 'nph-site-';
    public const AWARDEE_PREFIX = 'awardee-';
    public const ADMIN_GROUP = 'site-admin';
    public const NPH_ADMIN_GROUP = 'nph-site-admin';
    public const TWOFACTOR_GROUP = 'mfa_exception';
    public const TWOFACTOR_PREFIX = 'x-site-';
    public const ADMIN_DV = 'dv-admin';
    public const BIOBANK_GROUP = 'biospecimen-non-pii';
    public const SCRIPPS_GROUP = 'scripps-non-pii';
    public const AWARDEE_SCRIPPS = 'stsi';
    public const READ_ONLY_GROUP = 'tactisview';

    public const DEFAULT_TIMEZONE = 'America/New_York';

    private $googleUser;
    private $groups;
    private $sites;
    private $nphSites;
    private $awardees;
    private $adminAccess;
    private $nphAdminAccess;
    private $info;
    private $timezone;
    private $lastLogin;
    private $sessionInfo;
    private $adminDvAccess;
    private $biobankAccess;
    private $scrippsAccess;
    private $scrippsAwardee;
    private $readOnlyGroups;

    public function __construct($googleUser, array $groups, $info = null, $timezone = null, $sessionInfo = null)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->info = $info;
        $this->timezone = is_null($timezone) && isset($info['timezone']) ? $info['timezone'] : $timezone;
        $this->sessionInfo = $sessionInfo;
        $this->sites = $this->computeSites('hpo');
        $this->nphSites = $this->computeSites('nph');
        $this->awardees = $this->computeAwardees();
        $this->adminAccess = $this->computeAdminAccess('hpo');
        $this->nphAdminAccess = $this->computeAdminAccess('nph');
        $this->adminDvAccess = $this->computeAdminDvAccess();
        $this->biobankAccess = $this->computeBiobankAccess();
        $this->scrippsAccess = $this->computeScrippsAccess();
        $this->readOnlyGroups = $this->computeReadOnlyGroups();
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getInfo()
    {
        return $this->info;
    }

    private function computeSites($siteType)
    {
        $sites = [];
        $sitePrefix = $siteType === 'hpo' ? self::SITE_PREFIX : self::SITE_NPH_PREFIX;
        // site membership is determined by the user's groups
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), $sitePrefix) === 0) {
                $id = preg_replace('/@.*$/', '', $group->getEmail());
                // Prevent admin group from being added to the sites list as it has the same site prefix.
                if ($id !== self::NPH_ADMIN_GROUP) {
                    $id = str_replace($sitePrefix, '', $id);
                    $sites[] = (object)[
                        'email' => $group->getEmail(),
                        'name' => $group->getName(),
                        'id' => $id
                    ];
                }
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

    private function computeAdminAccess($type)
    {
        $hasAccess = false;
        $groupPrefix = $type === 'hpo' ? self::ADMIN_GROUP : self::NPH_ADMIN_GROUP;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), $groupPrefix . '@') === 0) {
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

    private function computeReadOnlyGroups()
    {
        $readOnlyGroups = [];
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::READ_ONLY_GROUP . '@') === 0) {
                $id = preg_replace('/@.*$/', '', $group->getEmail());
                $readOnlyGroups[] = (object)[
                    'email' => $group->getEmail(),
                    'name' => $group->getName(),
                    'id' => $id
                ];
            }
        }
        return $readOnlyGroups;
    }


    public function hasTwoFactorAuth(): bool
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

    public function getNphSites(): array
    {
        return $this->nphSites;
    }

    public function getAwardees()
    {
        return $this->awardees;
    }

    public function getSite($email, $siteType = 'sites')
    {
        $site = null;
        foreach ($this->{$siteType} as $s) {
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

    public function belongsToSite($email, $siteType = 'sites')
    {
        $belongs = false;
        foreach ($this->{$siteType} as $site) {
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
        if (count($this->nphSites) > 0) {
            $roles[] = 'ROLE_NPH_USER';
        }
        if (count($this->awardees) > 0) {
            $roles[] = 'ROLE_AWARDEE';
        }
        if ($this->adminAccess) {
            $roles[] = 'ROLE_ADMIN';
        }
        if ($this->nphAdminAccess) {
            $roles[] = 'ROLE_NPH_ADMIN';
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
        if (!empty($this->sessionInfo['managegroups']) || !empty($this->sessionInfo['managegroupsnph'])) {
            $roles[] = 'ROLE_MANAGE_USERS';
        }
        if (count($this->readOnlyGroups)) {
            $roles[] = 'ROLE_READ_ONLY';
        }
        return $roles;
    }

    public function getRoles(): array
    {
        return $this->getUserRoles($this->getAllRoles(), $this->sessionInfo['site'], $this->sessionInfo['awardee']);
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getUsername(): ?string
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

    public function getSiteFromId(string $siteId, string $siteType = 'sites')
    {
        $site = null;
        foreach ($this->$siteType as $s) {
            if ($s->id === $siteId) {
                $site = $s;
                break;
            }
        }
        return $site;
    }

    private function getUserRoles($roles, $site, $awardee)
    {
        if (!empty($site)) {
            if (($key = array_search('ROLE_AWARDEE', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (($key = array_search('ROLE_AWARDEE_SCRIPPS', $roles)) !== false) {
                unset($roles[$key]);
            }
        }
        if (!empty($awardee)) {
            if (($key = array_search('ROLE_USER', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (isset($awardee->id) && $awardee->id !== User::AWARDEE_SCRIPPS && ($key = array_search('ROLE_AWARDEE_SCRIPPS', $roles)) !== false) {
                unset($roles[$key]);
            }
        }
        return $roles;
    }

    public function getReadOnlyGroups()
    {
        return $this->readOnlyGroups;
    }

    public function getReadOnlyGroup($email)
    {
        $readOnlyGroup = null;
        foreach ($this->readOnlyGroups as $g) {
            if ($g->email === $email) {
                $readOnlyGroup = $g;
                break;
            }
        }
        return $readOnlyGroup;
    }

    public function getReadOnlyGroupFromId($groupId)
    {
        $readOnlyGroup = null;
        foreach ($this->readOnlyGroups as $g) {
            if ($g->id === $groupId) {
                $readOnlyGroup = $g;
                break;
            }
        }
        return $readOnlyGroup;
    }

    public function getGroup(string $email, string $siteType = 'sites')
    {
        $group = $this->getSite($email);
        if ($group) {
            return $group;
        }
        return $this->getReadOnlyGroup($email);
    }

    public function getGroupFromId(string $groupId, string $siteType = 'sites')
    {
        $group = $this->getSiteFromId($groupId, $siteType);
        if ($group) {
            return $group;
        }
        return $this->getReadOnlyGroupFromId($groupId);
    }
}
