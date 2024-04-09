<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: 'App\Repository\UserRepository')]
class User
{
    public const SITE_PREFIX = 'hpo-site-';
    public const AWARDEE_PREFIX = 'awardee-';
    public const ADMIN_GROUP = 'site-admin';
    public const TWOFACTOR_GROUP = 'mfa_exception';
    public const TWOFACTOR_PREFIX = 'x-site-';
    public const ADMIN_DV = 'dv-admin';
    public const BIOBANK_GROUP = 'biospecimen-non-pii';
    public const SCRIPPS_GROUP = 'scripps-non-pii';
    public const AWARDEE_SCRIPPS = 'stsi';
    public const PROGRAM_NPH = 'nph';
    public const PROGRAM_HPO = 'hpo';
    public const PROGRAMS = ['hpo', 'nph'];
    public const SALESFORCE = 'salesforce';

    public const DEFAULT_TIMEZONE = 'America/New_York';

    public static array $timezones = [
        1 => 'America/Puerto_Rico',
        2 => 'America/New_York',
        3 => 'America/Chicago',
        4 => 'America/Denver',
        5 => 'America/Phoenix',
        6 => 'America/Los_Angeles',
        7 => 'America/Anchorage',
        8 => 'Pacific/Honolulu'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $email;

    #[ORM\Column(type: 'string', length: 255)]
    private $google_id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $timezone;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $lastLogin;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->google_id;
    }

    public function setGoogleId(string $google_id): self
    {
        $this->google_id = $google_id;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getLastlogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public static function removeUserRoles(array $removeRoles, array &$roles): void
    {
        foreach ($removeRoles as $removeRole) {
            if (($key = array_search($removeRole, $roles)) !== false) {
                unset($roles[$key]);
            }
        }
    }

    public function getTimezoneId(): int
    {
        if ($this->timezone) {
            $timezoneId = array_search($this->timezone, self::$timezones);
            return ($timezoneId !== false) ? $timezoneId : 2;
        }
        return 2;
    }
}
