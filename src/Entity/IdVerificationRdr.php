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
    private int $id;

    #[ORM\Column(length: 255)]
    private string $participantId;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column]
    private int $userId;

    #[ORM\Column(length: 255)]
    private string $siteName;

    #[ORM\Column(length: 255)]
    private string $siteId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $verifiedDate;

    #[ORM\Column(length: 255)]
    private string $verificationType;

    #[ORM\Column(length: 255)]
    private string $visitType;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdTs;

    #[ORM\Column(nullable: true)]
    private ?int $insertId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantId(): string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): static
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getEmail(): string
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

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = $siteName;

        return $this;
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function setSiteId(string $siteId): static
    {
        $this->siteId = $siteId;

        return $this;
    }

    public function getVerifiedDate(): ?\DateTimeInterface
    {
        return $this->verifiedDate;
    }

    public function setVerifiedDate(\DateTimeInterface $verifiedDate): static
    {
        $this->verifiedDate = $verifiedDate;

        return $this;
    }

    public function getVerificationType(): string
    {
        return $this->verificationType;
    }

    public function setVerificationType(string $verificationType): static
    {
        $this->verificationType = $verificationType;

        return $this;
    }

    public function getVisitType(): string
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

    public function getInsertId(): ?int
    {
        return $this->insertId;
    }

    public function setInsertId(?int $insertId): static
    {
        $this->insertId = $insertId;

        return $this;
    }
}
