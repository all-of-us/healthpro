<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(name="users")
 */
class User
{
    const SITE_PREFIX = 'hpo-site-';
    const AWARDEE_PREFIX = 'awardee-';
    const DASHBOARD_GROUP = 'admin-dashboard';
    const ADMIN_GROUP = 'site-admin';
    const TWOFACTOR_GROUP = 'mfa_exception';
    const TWOFACTOR_PREFIX = 'x-site-';
    const ADMIN_DV = 'dv-admin';
    const BIOBANK_GROUP = 'biospecimen-non-pii';
    const SCRIPPS_GROUP = 'scripps-non-pii';
    const AWARDEE_SCRIPPS = 'stsi';

    const DEFAULT_TIMEZONE = 'America/New_York';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $google_id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $timezone;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
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
}
