<?php

namespace App\Security;

use App\Drc\GoogleUser;
use App\Drc\SalesforceUser;
use App\Entity\User as UserEntity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @phpstan-type GroupInfo object{email: string, name: string, id: string}
 * @phpstan-type UserInfo array<string, mixed>
 * @phpstan-type SessionInfo array<string, mixed>
 */
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
    public const NPH_BIOBANK_GROUP = 'nph-biospecimen-non-pii';
    public const SCRIPPS_GROUP = 'scripps-non-pii';
    public const AWARDEE_SCRIPPS = 'stsi';
    public const READ_ONLY_GROUP = 'tactisview';
    public const DEFAULT_TIMEZONE = 'America/New_York';
    public const HPO_TYPE = 'hpo';
    public const NPH_TYPE = 'nph';

    private GoogleUser|SalesforceUser|MockUser $googleUser;

    /** @var list<mixed> */
    private array $groups;

    /** @var list<GroupInfo> */
    private array $sites;

    /** @var list<GroupInfo> */
    private array $nphSites;

    /** @var list<GroupInfo> */
    private array $awardees;

    private bool $adminAccess;
    private bool $nphAdminAccess;

    /** @var UserInfo */
    private array $info;

    private ?string $timezone;
    private ?\DateTimeInterface $lastLogin = null;

    /** @var SessionInfo */
    private array $sessionInfo;

    private bool $adminDvAccess;
    private bool $biobankAccess;
    private bool $nphBiobankAccess;
    private bool $scrippsAccess;
    private bool $scrippsAwardee = false;

    /** @var list<GroupInfo> */
    private array $readOnlyGroups;

    /**
     * @param list<mixed>        $groups
     * @param UserInfo|null      $info
     * @param SessionInfo|null   $sessionInfo
     */
    public function __construct(GoogleUser|SalesforceUser|MockUser $googleUser, array $groups, ?array $info = null, ?string $timezone = null, ?array $sessionInfo = null)
    {
        $this->googleUser = $googleUser;
        $this->groups = $groups;
        $this->info = $info ?? [];
        $this->timezone = $timezone ?? ($this->info['timezone'] ?? null);
        $this->sessionInfo = $sessionInfo ?? [];
        $this->sites = $this->computeSites(self::HPO_TYPE);
        $this->nphSites = $this->computeSites(self::NPH_TYPE);
        $this->awardees = $this->computeAwardees();
        $this->adminAccess = $this->computeAdminAccess(self::HPO_TYPE);
        $this->nphAdminAccess = $this->computeAdminAccess(self::NPH_TYPE);
        $this->adminDvAccess = $this->computeAdminDvAccess();
        $this->biobankAccess = $this->computeBiobankAccess(self::HPO_TYPE);
        $this->nphBiobankAccess = $this->computeBiobankAccess(self::NPH_TYPE);
        $this->scrippsAccess = $this->computeScrippsAccess();
        $this->readOnlyGroups = $this->computeReadOnlyGroups();
    }

    /**
     * @return list<mixed>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return UserInfo
     */
    public function getInfo(): array
    {
        return $this->info;
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

    /**
     * @return list<GroupInfo>
     */
    public function getSites(): array
    {
        return $this->sites;
    }

    /**
     * @return list<GroupInfo>
     */
    public function getNphSites(): array
    {
        return $this->nphSites;
    }

    /**
     * @return list<GroupInfo>
     */
    public function getAwardees(): array
    {
        return $this->awardees;
    }

    /**
     * @return GroupInfo|null
     */
    public function getSite(string $email, string $siteType = 'sites'): ?object
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

    /**
     * @return GroupInfo|null
     */
    public function getAwardee(string $email): ?object
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

    public function belongsToSite(string $email, string $siteType = 'sites'): bool
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

    public function belongsToAwardee(string $email): bool
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

    public function getGoogleUser(): GoogleUser|SalesforceUser|MockUser
    {
        return $this->googleUser;
    }

    /**
     * @return list<string>
     */
    public function getAllRoles(): array
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
        if ($this->nphBiobankAccess) {
            $roles[] = 'ROLE_NPH_BIOBANK';
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
        if (!empty($this->sessionInfo['managegroupsnph'])) {
            $roles[] = 'ROLE_MANAGE_USERS_NPH';
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

    public function getUserIdentifier(): string
    {
        return $this->googleUser->getEmail();
    }

    public function getEmail(): string
    {
        return $this->googleUser->getEmail();
    }

    public function eraseCredentials(): void
    {
        // we don't actually store any credentials
    }

    public function getTimezone(bool $useDefault = true): ?string
    {
        if (!$this->timezone) {
            return $useDefault ? self::DEFAULT_TIMEZONE : null;
        }
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getLastlogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getId(): int|false
    {
        if (isset($this->info['id'])) {
            return (int) $this->info['id'];
        }
        return false;
    }

    /**
     * @return GroupInfo|null
     */
    public function getSiteFromId(string $siteId, string $siteType = 'sites'): ?object
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

    /**
     * @return list<GroupInfo>
     */
    public function getReadOnlyGroups(): array
    {
        return $this->readOnlyGroups;
    }

    /**
     * @return GroupInfo|null
     */
    public function getReadOnlyGroup(string $email): ?object
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

    /**
     * @return GroupInfo|null
     */
    public function getReadOnlyGroupFromId(string $groupId): ?object
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

    /**
     * @return GroupInfo|null
     */
    public function getGroup(string $email, string $siteType = 'sites'): ?object
    {
        $group = $this->getSite($email, $siteType);
        if ($group) {
            return $group;
        }
        return $this->getReadOnlyGroup($email);
    }

    /**
     * @return GroupInfo|null
     */
    public function getGroupFromId(string $groupId, string $siteType = 'sites'): ?object
    {
        $group = $this->getSiteFromId($groupId, $siteType);
        if ($group) {
            return $group;
        }
        return $this->getReadOnlyGroupFromId($groupId);
    }

    /**
     * @return list<GroupInfo>
     */
    private function computeSites(string $siteType): array
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
                    if ($siteType === self::HPO_TYPE && method_exists($group, 'getId') && !empty($group->getId())) {
                        $id = $group->getId();
                    }
                    $sites[] = (object) [
                        'email' => $group->getEmail(),
                        'name' => $group->getName(),
                        'id' => $id
                    ];
                }
            }
        }
        return $sites;
    }

    /**
     * @return list<GroupInfo>
     */
    private function computeAwardees(): array
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
                // Check for scripps awardee
                if ($id === self::AWARDEE_SCRIPPS) {
                    $this->scrippsAwardee = true;
                }
            }
        }
        return $awardees;
    }

    private function computeAdminAccess(string $type): bool
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

    private function computeAdminDvAccess(): bool
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::ADMIN_DV . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }

    private function computeBiobankAccess(string $type): bool
    {
        $groupPrefix = $type === 'hpo' ? self::BIOBANK_GROUP : self::NPH_BIOBANK_GROUP;
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), $groupPrefix . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }

    private function computeScrippsAccess(): bool
    {
        $hasAccess = false;
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::SCRIPPS_GROUP . '@') === 0) {
                $hasAccess = true;
            }
        }
        return $hasAccess;
    }

    /**
     * @return list<GroupInfo>
     */
    private function computeReadOnlyGroups(): array
    {
        $readOnlyGroups = [];
        foreach ($this->groups as $group) {
            if (strpos($group->getEmail(), self::READ_ONLY_GROUP . '@') === 0) {
                $id = preg_replace('/@.*$/', '', $group->getEmail());
                $readOnlyGroups[] = (object) [
                    'email' => $group->getEmail(),
                    'name' => $group->getName(),
                    'id' => $id
                ];
            }
        }
        return $readOnlyGroups;
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function getUserRoles(array $roles, mixed $site, mixed $awardee): array
    {
        if (!empty($site)) {
            UserEntity::removeUserRoles(['ROLE_AWARDEE', 'ROLE_AWARDEE_SCRIPPS'], $roles);
            if ($this->sessionInfo['program'] === UserEntity::PROGRAM_NPH) {
                UserEntity::removeUserRoles(['ROLE_USER'], $roles);
            } else {
                UserEntity::removeUserRoles(['ROLE_NPH_USER'], $roles);
            }
        }
        if (!empty($awardee)) {
            UserEntity::removeUserRoles(['ROLE_USER', 'ROLE_NPH_USER'], $roles);
            if (isset($awardee->id) && $awardee->id !== User::AWARDEE_SCRIPPS) {
                UserEntity::removeUserRoles(['ROLE_AWARDEE_SCRIPPS'], $roles);
            }
        }
        return $roles;
    }
}
