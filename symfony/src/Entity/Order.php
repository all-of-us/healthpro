<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrderRepository")
 * @ORM\Table(name="`orders`")
 */
class Order
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $rdrId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $biobankId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $orderId;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $mayoId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestedSamples;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $printedTs;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $collectedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $collectedSite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $collectedTs;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $collectedSamples;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $collectedNotes;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $processedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $processedSite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $processedTs;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $processedSamples;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $processedSamplesTs;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $processedCentrifugeType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $processedNotes;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $finalizedUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finalizedTs;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSamples;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $finalizedNotes;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $fedexTracking;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $version;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $biobankFinalized;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $biobankChanges;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\OrderHistory", cascade={"persist", "remove"})
     */
    private $history;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSite;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getRdrId(): ?string
    {
        return $this->rdrId;
    }

    public function setRdrId(string $rdrId): self
    {
        $this->rdrId = $rdrId;

        return $this;
    }

    public function getBiobankId(): ?string
    {
        return $this->biobankId;
    }

    public function setBiobankId(string $biobankId): self
    {
        $this->biobankId = $biobankId;

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

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getMayoId(): ?string
    {
        return $this->mayoId;
    }

    public function setMayoId(?string $mayoId): self
    {
        $this->mayoId = $mayoId;

        return $this;
    }

    public function getRequestedSamples(): ?string
    {
        return $this->requestedSamples;
    }

    public function setRequestedSamples(?string $requestedSamples): self
    {
        $this->requestedSamples = $requestedSamples;

        return $this;
    }

    public function getPrintedTs(): ?\DateTimeInterface
    {
        return $this->printedTs;
    }

    public function setPrintedTs(?\DateTimeInterface $printedTs): self
    {
        $this->printedTs = $printedTs;

        return $this;
    }

    public function getCollectedUser(): ?User
    {
        return $this->collectedUser;
    }

    public function setCollectedUser(?User $collectedUser): self
    {
        $this->collectedUser = $collectedUser;

        return $this;
    }

    public function getCollectedSite(): ?string
    {
        return $this->collectedSite;
    }

    public function setCollectedSite(?string $collectedSite): self
    {
        $this->collectedSite = $collectedSite;

        return $this;
    }

    public function getCollectedTs(): ?\DateTimeInterface
    {
        return $this->collectedTs;
    }

    public function setCollectedTs(?\DateTimeInterface $collectedTs): self
    {
        $this->collectedTs = $collectedTs;

        return $this;
    }

    public function getCollectedSamples(): ?string
    {
        return $this->collectedSamples;
    }

    public function setCollectedSamples(?string $collectedSamples): self
    {
        $this->collectedSamples = $collectedSamples;

        return $this;
    }

    public function getCollectedNotes(): ?string
    {
        return $this->collectedNotes;
    }

    public function setCollectedNotes(?string $collectedNotes): self
    {
        $this->collectedNotes = $collectedNotes;

        return $this;
    }

    public function getProcessedUser(): ?User
    {
        return $this->processedUser;
    }

    public function setProcessedUser(?User $processedUser): self
    {
        $this->processedUser = $processedUser;

        return $this;
    }

    public function getProcessedSite(): ?string
    {
        return $this->processedSite;
    }

    public function setProcessedSite(?string $processedSite): self
    {
        $this->processedSite = $processedSite;

        return $this;
    }

    public function getProcessedTs(): ?\DateTimeInterface
    {
        return $this->processedTs;
    }

    public function setProcessedTs(?\DateTimeInterface $processedTs): self
    {
        $this->processedTs = $processedTs;

        return $this;
    }

    public function getProcessedSamples(): ?string
    {
        return $this->processedSamples;
    }

    public function setProcessedSamples(?string $processedSamples): self
    {
        $this->processedSamples = $processedSamples;

        return $this;
    }

    public function getProcessedSamplesTs(): ?string
    {
        return $this->processedSamplesTs;
    }

    public function setProcessedSamplesTs(?string $processedSamplesTs): self
    {
        $this->processedSamplesTs = $processedSamplesTs;

        return $this;
    }

    public function getProcessedCentrifugeType(): ?string
    {
        return $this->processedCentrifugeType;
    }

    public function setProcessedCentrifugeType(?string $processedCentrifugeType): self
    {
        $this->processedCentrifugeType = $processedCentrifugeType;

        return $this;
    }

    public function getProcessedNotes(): ?string
    {
        return $this->processedNotes;
    }

    public function setProcessedNotes(?string $processedNotes): self
    {
        $this->processedNotes = $processedNotes;

        return $this;
    }

    public function getFinalizedUser(): ?User
    {
        return $this->finalizedUser;
    }

    public function setFinalizedUser(?User $finalizedUser): self
    {
        $this->finalizedUser = $finalizedUser;

        return $this;
    }

    public function getFinalizedTs(): ?\DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?\DateTimeInterface $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

        return $this;
    }

    public function getFinalizedSamples(): ?string
    {
        return $this->finalizedSamples;
    }

    public function setFinalizedSamples(string $finalizedSamples): self
    {
        $this->finalizedSamples = $finalizedSamples;

        return $this;
    }

    public function getFinalizedNotes(): ?string
    {
        return $this->finalizedNotes;
    }

    public function setFinalizedNotes(?string $finalizedNotes): self
    {
        $this->finalizedNotes = $finalizedNotes;

        return $this;
    }

    public function getFedexTracking(): ?string
    {
        return $this->fedexTracking;
    }

    public function setFedexTracking(?string $fedexTracking): self
    {
        $this->fedexTracking = $fedexTracking;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getBiobankFinalized(): ?bool
    {
        return $this->biobankFinalized;
    }

    public function setBiobankFinalized(?bool $biobankFinalized): self
    {
        $this->biobankFinalized = $biobankFinalized;

        return $this;
    }

    public function getBiobankChanges(): ?string
    {
        return $this->biobankChanges;
    }

    public function setBiobankChanges(?string $biobankChanges): self
    {
        $this->biobankChanges = $biobankChanges;

        return $this;
    }

    public function getHistory(): ?OrderHistory
    {
        return $this->history;
    }

    public function setHistoryId(?OrderHistory $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function getFinalizedSite(): ?string
    {
        return $this->finalizedSite;
    }

    public function setFinalizedSite(?string $finalizedSite): self
    {
        $this->finalizedSite = $finalizedSite;

        return $this;
    }
}
