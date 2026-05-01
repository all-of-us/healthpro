<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class IdVerification
{
    public const PEDIATRIC_VISIT = 'PEDIATRIC_VISIT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $site;

    #[ORM\Column(type: 'string', length: 50)]
    private string $participantId;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $verifiedDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $verificationType = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $visitType = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\ManyToOne(targetEntity: IdVerificationImport::class)]
    private ?IdVerificationImport $import = null;

    #[ORM\Column(nullable: true)]
    private ?bool $GuardianVerified = null;

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

    public function isGuardianVerified(): ?bool
    {
        return $this->GuardianVerified;
    }

    public function setGuardianVerified(?bool $GuardianVerified): static
    {
        $this->GuardianVerified = $GuardianVerified;

        return $this;
    }
}
