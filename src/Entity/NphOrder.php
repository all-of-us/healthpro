<?php

namespace App\Entity;

use App\Repository\NphOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NphOrderRepository::class)
 */
class NphOrder
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $orderId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $timepoint;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $visitType;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $cancelledTs;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $cancelledUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $cancelledSite;

    /**
     * @ORM\OneToMany(targetEntity=NphSample::class, mappedBy="nphOrder")
     */
    private $nphSamples;

    public function __construct()
    {
        $this->nphSamples = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;

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

    public function getTimepoint(): ?string
    {
        return $this->timepoint;
    }

    public function setTimepoint(string $timepoint): self
    {
        $this->timepoint = $timepoint;

        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(string $visitType): self
    {
        $this->visitType = $visitType;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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

    public function getCancelledTs(): ?\DateTimeInterface
    {
        return $this->cancelledTs;
    }

    public function setCancelledTs(?\DateTimeInterface $cancelledTs): self
    {
        $this->cancelledTs = $cancelledTs;

        return $this;
    }

    public function getCancelledUser(): ?User
    {
        return $this->cancelledUser;
    }

    public function setCancelledUser(?User $cancelledUser): self
    {
        $this->cancelledUser = $cancelledUser;

        return $this;
    }

    public function getCancelledSite(): ?string
    {
        return $this->cancelledSite;
    }

    public function setCancelledSite(?string $cancelledSite): self
    {
        $this->cancelledSite = $cancelledSite;

        return $this;
    }

    /**
     * @return Collection|NphSample[]
     */
    public function getNphSamples(): Collection
    {
        return $this->nphSamples;
    }

    public function addNphSample(NphSample $nphSample): self
    {
        if (!$this->nphSamples->contains($nphSample)) {
            $this->nphSamples[] = $nphSample;
            $nphSample->setNphOrder($this);
        }

        return $this;
    }

    public function removeNphSample(NphSample $nphSample): self
    {
        if ($this->nphSamples->removeElement($nphSample)) {
            // set the owning side to null (unless already changed)
            if ($nphSample->getNphOrder() === $this) {
                $nphSample->setNphOrder(null);
            }
        }

        return $this;
    }
}
