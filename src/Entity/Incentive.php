<?php

namespace App\Entity;

use App\Repository\IncentiveRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IncentiveRepository::class)
 */
class Incentive
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="datetime")
     */
    private $incentiveDateGiven;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $incentiveType;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $otherIncentiveType;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $incentiveOccurrence;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $otherIncentiveOccurrence;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $incentiveAmount;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $otherIncentiveAmount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $giftCardType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $notes;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $amendedUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $amendedTs;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $cancelledUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $cancelledTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $rdrTs;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIncentiveDateGiven(): ?\DateTimeInterface
    {
        return $this->incentiveDateGiven;
    }

    public function setIncentiveDateGiven(\DateTimeInterface $incentiveDateGiven): self
    {
        $this->incentiveDateGiven = $incentiveDateGiven;

        return $this;
    }

    public function getIncentiveType(): ?string
    {
        return $this->incentiveType;
    }

    public function setIncentiveType(string $incentiveType): self
    {
        $this->incentiveType = $incentiveType;

        return $this;
    }

    public function getOtherIncentiveType(): ?string
    {
        return $this->otherIncentiveType;
    }

    public function setOtherIncentiveType(?string $otherIncentiveType): self
    {
        $this->otherIncentiveType = $otherIncentiveType;

        return $this;
    }

    public function getIncentiveOccurrence(): ?string
    {
        return $this->incentiveOccurrence;
    }

    public function setIncentiveOccurrence(string $incentiveOccurrence): self
    {
        $this->incentiveOccurrence = $incentiveOccurrence;

        return $this;
    }

    public function getOtherIncentiveOccurrence(): ?string
    {
        return $this->otherIncentiveOccurrence;
    }

    public function setOtherIncentiveOccurrence(?string $otherIncentiveOccurrence): self
    {
        $this->otherIncentiveOccurrence = $otherIncentiveOccurrence;

        return $this;
    }

    public function getIncentiveAmount(): ?string
    {
        return $this->incentiveAmount;
    }

    public function setIncentiveAmount(?string $incentiveAmount): self
    {
        $this->incentiveAmount = $incentiveAmount;

        return $this;
    }

    public function getOtherIncentiveAmount(): ?int
    {
        return $this->otherIncentiveAmount;
    }

    public function setOtherIncentiveAmount(?int $otherIncentiveAmount): self
    {
        $this->otherIncentiveAmount = $otherIncentiveAmount;

        return $this;
    }

    public function getGiftCardType(): ?string
    {
        return $this->giftCardType;
    }

    public function setGiftCardType(?string $giftCardType): self
    {
        $this->giftCardType = $giftCardType;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    public function getAmendedUser(): ?User
    {
        return $this->amendedUser;
    }

    public function setAmendedUser(?User $amendedUser): self
    {
        $this->amendedUser = $amendedUser;

        return $this;
    }

    public function getAmendedTs(): ?\DateTimeInterface
    {
        return $this->amendedTs;
    }

    public function setAmendedTs(?\DateTimeInterface $amendedTs): self
    {
        $this->amendedTs = $amendedTs;

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

    public function getCancelledTs(): ?\DateTimeInterface
    {
        return $this->cancelledTs;
    }

    public function setCancelledTs(?\DateTimeInterface $cancelledTs): self
    {
        $this->cancelledTs = $cancelledTs;

        return $this;
    }

    public function getRdrTs(): ?\DateTimeInterface
    {
        return $this->rdrTs;
    }

    public function setRdrTs(?\DateTimeInterface $rdrTs): self
    {
        $this->rdrTs = $rdrTs;

        return $this;
    }
}
