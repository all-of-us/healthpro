<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SiteRepository")
 */
class Site
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $siteId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $organizationId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $awardeeId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $googleGroup;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mayolinkAccount;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $timezone;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $organization;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $awardee;

    /**
     * @ORM\Column(type="string", length=512, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $centrifugeType;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $workqueueDownload;

    /**
     * @ORM\Column(type="smallint")
     */
    private $deleted;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSiteId(): ?string
    {
        return $this->siteId;
    }

    public function setSiteId(?string $siteId): self
    {
        $this->site_id = $siteId;

        return $this;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(?string $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function getAwardeeId(): ?string
    {
        return $this->awardeeId;
    }

    public function setAwardeeId(?string $awardeeId): self
    {
        $this->awardeeId = $awardeeId;

        return $this;
    }

    public function getGoogleGroup(): ?string
    {
        return $this->googleGroup;
    }

    public function setGoogleGroup(string $googleGroup): self
    {
        $this->google_group = $googleGroup;

        return $this;
    }

    public function getMayolinkAccount(): ?string
    {
        return $this->mayolinkAccount;
    }

    public function setMayolinkAccount(?string $mayolinkAccount): self
    {
        $this->mayolinkAccount = $mayolinkAccount;

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

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAwardee(): ?string
    {
        return $this->awardee;
    }

    public function setAwardee(?string $awardee): self
    {
        $this->awardee = $awardee;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getCentrifugeType(): ?string
    {
        return $this->centrifugeType;
    }

    public function setCentrifugeType(?string $centrifugeType): self
    {
        $this->centrifugeType = $centrifugeType;

        return $this;
    }

    public function getWorkqueueDownload(): ?string
    {
        return $this->workqueueDownload;
    }

    public function setWorkqueueDownload(string $workqueueDownload): self
    {
        $this->workqueueDownload = $workqueueDownload;

        return $this;
    }

    public function getDeleted(): ?int
    {
        return $this->deleted;
    }

    public function setDeleted(int $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }
}
