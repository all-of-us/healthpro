<?php

namespace App\Entity;

use App\Repository\UserTimezoneAuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTimezoneAuditLogRepository::class)]
class UserTimezoneAuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previousTimezone = null;

    #[ORM\Column(length: 255)]
    private string $currentTimezone;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $modifiedTs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPreviousTimezone(): ?string
    {
        return $this->previousTimezone;
    }

    public function setPreviousTimezone(?string $previousTimezone): static
    {
        $this->previousTimezone = $previousTimezone;

        return $this;
    }

    public function getCurrentTimezone(): ?string
    {
        return $this->currentTimezone;
    }

    public function setCurrentTimezone(string $currentTimezone): static
    {
        $this->currentTimezone = $currentTimezone;

        return $this;
    }

    public function getModifiedTs(): ?\DateTimeInterface
    {
        return $this->modifiedTs;
    }

    public function setModifiedTs(\DateTimeInterface $modifiedTs): static
    {
        $this->modifiedTs = $modifiedTs;

        return $this;
    }
}
