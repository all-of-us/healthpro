<?php

namespace App\Entity;

use App\Repository\FeatureNotificationUserMapRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeatureNotificationUserMapRepository::class)]
class FeatureNotificationUserMap
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: FeatureNotification::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FeatureNotification $featureNotification;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getFeatureNotification(): ?FeatureNotification
    {
        return $this->featureNotification;
    }

    public function setFeatureNotification(FeatureNotification $featureNotification): self
    {
        $this->featureNotification = $featureNotification;

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
}
