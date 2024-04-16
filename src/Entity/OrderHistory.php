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
    private $id;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Order', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $order;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $user;

    #[ORM\Column(type: 'string', length: 50)]
    private $site;

    #[ORM\Column(type: 'string', length: 50)]
    private $type;

    #[ORM\Column(type: 'text')]
    private $reason;

    #[ORM\Column(type: 'datetime')]
    private $createdTs;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $createdTimezoneId;

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

    public function getReasonDisplayText()
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
        $this->$samplesVersion = $samplesVersion;

        return $this;
    }
}
