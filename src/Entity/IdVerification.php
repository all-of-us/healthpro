<?php

namespace App\Entity;

use App\Repository\IdVerificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class IdVerification
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $site;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $verifiedDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $verificationType;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $visitType;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\ManyToOne(targetEntity=IdVerificationImport::class)
     */
    private $import;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getVerifiedDate(): ?\DateTimeInterface
    {
        return $this->verifiedDate;
    }

    public function setVerifiedDate(\DateTimeInterface $verifiedDate): self
    {
        $this->verifiedDate = $verifiedDate;

        return $this;
    }

    public function getVerificationType(): ?string
    {
        return $this->verificationType;
    }

    public function setVerificationType(?string $verificationType): self
    {
        $this->verificationType = $verificationType;

        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(?string $visitType): self
    {
        $this->visitType = $visitType;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getImport(): ?IdVerificationImport
    {
        return $this->import;
    }

    public function setImport(?IdVerificationImport $import): self
    {
        $this->import = $import;

        return $this;
    }
}
