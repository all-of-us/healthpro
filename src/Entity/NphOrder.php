<?php

namespace App\Entity;

use App\Repository\NphOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NphOrderRepository::class)
 * @ORM\Table(name="nph_orders", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="order_id", columns={"order_id"})
 * })
 */
class NphOrder
{
    public const ORDER_CANCEL = 'cancel';
    public const ORDER_RESTORE = 'restore';
    public const ORDER_UNLOCK = 'unlock';

    public static $cancelReasons = [
        'Order created in error' => 'ORDER_CANCEL_ERROR',
        'Order created for wrong participant' => 'ORDER_CANCEL_WRONG_PARTICIPANT',
        'Labeling error identified after finalization' => 'ORDER_CANCEL_LABEL_ERROR',
        'Other' => 'OTHER'
    ];

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
     * @ORM\Column(type="string", length=10)
     */
    private $module;

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
    private $modifiedTs;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $modifiedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $modifiedSite;

    /**
     * @ORM\OneToMany(targetEntity=NphSample::class, mappedBy="nphOrder")
     */
    private $nphSamples;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $orderType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $metadata;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $modifyReason;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $modifyType;

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

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;

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

    public function getModifiedTs(): ?\DateTimeInterface
    {
        return $this->modifiedTs;
    }

    public function setModifiedTs(?\DateTimeInterface $modifiedTs): self
    {
        $this->modifiedTs = $modifiedTs;

        return $this;
    }

    public function getModifiedUser(): ?User
    {
        return $this->modifiedUser;
    }

    public function setModifiedUser(?User $modifiedUser): self
    {
        $this->modifiedUser = $modifiedUser;

        return $this;
    }

    public function getModifiedSite(): ?string
    {
        return $this->modifiedSite;
    }

    public function setModifiedSite(?string $modifiedSite): self
    {
        $this->modifiedSite = $modifiedSite;

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

    public function getOrderType(): ?string
    {
        return $this->orderType;
    }

    public function setOrderType(string $orderType): self
    {
        $this->orderType = $orderType;

        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getModifyReason(): ?string
    {
        return $this->modifyReason;
    }

    public function setModifyReason(?string $modifyReason): self
    {
        $this->modifyReason = $modifyReason;

        return $this;
    }

    public function getModifyType(): ?string
    {
        return $this->modifyType;
    }

    public function setModifyType(?string $modifyType): self
    {
        $this->modifyType = $modifyType;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->modifyType === 'cancel';
    }
}
