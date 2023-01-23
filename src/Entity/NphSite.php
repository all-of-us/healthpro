<?php

namespace App\Entity;

use App\Repository\NphSiteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="nph_sites")
 * @ORM\Entity(repositoryClass=NphSiteRepository::class)
 */
class NphSite
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $googleGroup;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $organizationId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $awardeeId;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="boolean")
     */
    private $deleted = false;

    /**
     * @ORM\Column(type="string", length=512, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mayolinkAccount;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
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

    public function getGoogleGroup(): ?string
    {
        return $this->googleGroup;
    }

    public function setGoogleGroup(string $googleGroup): self
    {
        $this->googleGroup = $googleGroup;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

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

    public function getMayolinkAccount(): ?string
    {
        return $this->mayolinkAccount;
    }

    public function setMayolinkAccount(?string $mayolinkAccount): self
    {
        $this->mayolinkAccount = $mayolinkAccount;

        return $this;
    }
}
