<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'evaluations_history')]
#[ORM\Entity(repositoryClass: 'App\Repository\MeasurementHistoryRepository')]
class MeasurementHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Measurement', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $evaluation;

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


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeasurement(): ?Measurement
    {
        return $this->evaluation;
    }

    public function setMeasurement(Measurement $measurement): self
    {
        $this->evaluation = $measurement;

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
}
