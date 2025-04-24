<?php

namespace App\Entity;

use App\Repository\NphAdminOrderEditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NphAdminOrderEditLogRepository::class)]
class NphAdminOrderEditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $orderId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $originalOrderGenerationTs = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedOrderGenerationTs = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdTs = null;

    #[ORM\Column]
    private ?int $originalOrderGenerationTimezoneId = null;

    #[ORM\Column]
    private ?int $updatedOrderGenerationTimezoneId = null;

    #[ORM\Column]
    private ?int $createdTimezoneId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOriginalOrderGenerationTs(): ?\DateTimeInterface
    {
        return $this->originalOrderGenerationTs;
    }

    public function setOriginalOrderGenerationTs(\DateTimeInterface $originalOrderGenerationTs): static
    {
        $this->originalOrderGenerationTs = $originalOrderGenerationTs;

        return $this;
    }

    public function getUpdatedOrderGenerationTs(): ?\DateTimeInterface
    {
        return $this->updatedOrderGenerationTs;
    }

    public function setUpdatedOrderGenerationTs(\DateTimeInterface $updatedOrderGenerationTs): static
    {
        $this->updatedOrderGenerationTs = $updatedOrderGenerationTs;

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

    public function getOriginalOrderGenerationTimezoneId(): ?int
    {
        return $this->originalOrderGenerationTimezoneId;
    }

    public function setOriginalOrderGenerationTimezoneId(int $originalOrderGenerationTimezoneId): static
    {
        $this->originalOrderGenerationTimezoneId = $originalOrderGenerationTimezoneId;

        return $this;
    }

    public function getUpdatedOrderGenerationTimezoneId(): ?int
    {
        return $this->updatedOrderGenerationTimezoneId;
    }

    public function setUpdatedOrderGenerationTimezoneId(int $updatedOrderGenerationTimezoneId): static
    {
        $this->updatedOrderGenerationTimezoneId = $updatedOrderGenerationTimezoneId;

        return $this;
    }

    public function getCreatedTimezoneId(): ?int
    {
        return $this->createdTimezoneId;
    }

    public function setCreatedTimezoneId(int $createdTimezoneId): static
    {
        $this->createdTimezoneId = $createdTimezoneId;

        return $this;
    }
}
