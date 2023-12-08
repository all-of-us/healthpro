<?php

namespace App\Entity;

use App\Repository\IdVerificationRdrRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IdVerificationRdrRepository::class)]
class IdVerificationRdr
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $participantId = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\Column(length: 255)]
    private ?string $siteName = null;

    #[ORM\Column]
    private ?int $siteId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $verificationDate = null;

    #[ORM\Column(length: 255)]
    private ?string $verificationType = null;

    #[ORM\Column(length: 255)]
    private ?string $visitType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdTs = null;

    #[ORM\Column(nullable: true)]
    private ?int $insertStatus = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): static
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getSiteName(): ?string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }

    public function setSiteId(int $siteId): static
    {
        $this->siteId = $siteId;

        return $this;
    }

    public function getVerificationDate(): ?\DateTimeInterface
    {
        return $this->verificationDate;
    }

    public function setVerificationDate(\DateTimeInterface $verificationDate): static
    {
        $this->verificationDate = $verificationDate;

        return $this;
    }

    public function getVerificationType(): ?string
    {
        return $this->verificationType;
    }

    public function setVerificationType(string $verificationType): static
    {
        $this->verificationType = $verificationType;

        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(string $visitType): static
    {
        $this->visitType = $visitType;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): static
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getInsertStatus(): ?int
    {
        return $this->insertStatus;
    }

    public function setInsertStatus(?int $insertStatus): static
    {
        $this->insertStatus = $insertStatus;

        return $this;
    }
}
