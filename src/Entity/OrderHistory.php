<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'orders_history')]
#[ORM\Entity(repositoryClass: 'App\Repository\OrderHistoryRepository')]
class OrderHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Order', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $site;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $createdTimezoneId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $samplesVersion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrderId(Order $order): self
    {
        $this->order = $order;

        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

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

    public function getCreatedTimezoneId(): ?int
    {
        return $this->createdTimezoneId;
    }

    public function setCreatedTimezoneId(?int $createdTimezoneId): self
    {
        $this->createdTimezoneId = $createdTimezoneId;

        return $this;
    }

    public function getReasonDisplayText(): string
    {
        $reasonDisplayText = array_search($this->getReason(), Order::$cancelReasons);
        return !empty($reasonDisplayText) ? $reasonDisplayText : 'Other (' . $this->getReason() . ')';
    }

    public function getSamplesVersion(): ?string
    {
        return $this->samplesVersion;
    }

    public function setSamplesVersion(?string $samplesVersion): static
    {
        $this->samplesVersion = $samplesVersion;

        return $this;
    }
}
