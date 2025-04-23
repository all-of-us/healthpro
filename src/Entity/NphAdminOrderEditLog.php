<?php

namespace App\Entity;

use App\Repository\NphAdminOrderEditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'nph_admin_orders_edit_log')]
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
    private ?\DateTimeInterface $originalOrderGenerationTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedOrderGenerationTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdTs = null;

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

    public function getOriginalOrderGenerationTime(): ?\DateTimeInterface
    {
        return $this->originalOrderGenerationTime;
    }

    public function setOriginalOrderGenerationTime(\DateTimeInterface $originalOrderGenerationTime): static
    {
        $this->originalOrderGenerationTime = $originalOrderGenerationTime;

        return $this;
    }

    public function getUpdatedOrderGenerationTime(): ?\DateTimeInterface
    {
        return $this->updatedOrderGenerationTime;
    }

    public function setUpdatedOrderGenerationTime(\DateTimeInterface $updatedOrderGenerationTime): static
    {
        $this->updatedOrderGenerationTime = $updatedOrderGenerationTime;

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
}
