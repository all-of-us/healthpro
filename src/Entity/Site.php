<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'sites')]
#[ORM\UniqueConstraint(name: 'site_id', columns: ['site_id'])]
#[ORM\Entity(repositoryClass: 'App\Repository\SiteRepository')]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean')]
    private bool $status;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $siteId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rdrSiteId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $organizationId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $awardeeId = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $googleGroup;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mayolinkAccount = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $organization = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $awardee = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $centrifugeType = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $workqueueDownload;

    #[ORM\Column(type: 'boolean')]
    private bool $ehrModificationProtocol = false;

    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $siteType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $dvModule = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\OneToOne(targetEntity: 'App\Entity\SiteSync', mappedBy: 'site', cascade: ['persist', 'remove'])]
    private ?SiteSync $siteSync = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool|int $status): self
    {
        $this->status = (bool) $status;

        return $this;
    }

    public function getName(): string
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
        $this->siteId = $siteId;

        return $this;
    }

    public function getRdrSiteId(): ?string
    {
        return $this->rdrSiteId;
    }

    public function setRdrSiteId(?string $rdrSiteId): self
    {
        $this->rdrSiteId = $rdrSiteId;

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

    public function getGoogleGroup(): string
    {
        return $this->googleGroup;
    }

    public function setGoogleGroup(string $googleGroup): self
    {
        $this->googleGroup = $googleGroup;

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

    public function getWorkqueueDownload(): string
    {
        return $this->workqueueDownload;
    }

    public function setWorkqueueDownload(string $workqueueDownload): self
    {
        $this->workqueueDownload = $workqueueDownload;

        return $this;
    }

    public function getEhrModificationProtocol(): bool
    {
        return $this->ehrModificationProtocol;
    }

    public function setEhrModificationProtocol(bool|int $ehrModificationProtocol): self
    {
        $this->ehrModificationProtocol = (bool) $ehrModificationProtocol;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool|int $deleted): self
    {
        $this->deleted = (bool) $deleted;

        return $this;
    }

    public function getSiteType(): ?string
    {
        return $this->siteType;
    }

    public function setSiteType(?string $siteType): self
    {
        $this->siteType = $siteType;

        return $this;
    }

    public function getDvModule(): ?string
    {
        return $this->dvModule;
    }

    public function setDvModule(?string $dvModule): self
    {
        $this->dvModule = $dvModule;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getSiteSync(): ?SiteSync
    {
        return $this->siteSync;
    }

    public function setSiteSync(SiteSync $siteSync): self
    {
        $this->siteSync = $siteSync;

        // set the owning side of the relation if necessary
        if ($siteSync->getSite() !== $this) {
            $siteSync->setSite($this);
        }

        return $this;
    }
}
