<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\IncentiveImportRowRepository')]
class IncentiveImportRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $participantId;

    #[ORM\ManyToOne(targetEntity: IncentiveImport::class)]
    #[ORM\JoinColumn(nullable: false)]
    private IncentiveImport $import;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $incentiveDateGiven = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $incentiveType = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $otherIncentiveType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $incentiveOccurrence = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $otherIncentiveOccurrence = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $incentiveAmount = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $giftCardType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $declined = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $rdrStatus = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userEmail = null;

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

    public function getImport(): ?IncentiveImport
    {
        return $this->import;
    }

    public function setImport(IncentiveImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getIncentiveDateGiven(): ?\DateTimeInterface
    {
        return $this->incentiveDateGiven;
    }

    public function setIncentiveDateGiven(?\DateTimeInterface $incentiveDateGiven): self
    {
        $this->incentiveDateGiven = $incentiveDateGiven;

        return $this;
    }

    public function getIncentiveType(): ?string
    {
        return $this->incentiveType;
    }

    public function setIncentiveType(?string $incentiveType): self
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

    public function getIncentiveAmount(): ?int
    {
        return $this->incentiveAmount;
    }

    public function setIncentiveAmount(?int $incentiveAmount): self
    {
        $this->incentiveAmount = $incentiveAmount;

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

    public function getDeclined(): bool
    {
        return (bool) $this->declined;
    }

    public function setDeclined(bool|int $declined): self
    {
        $this->declined = (int) $declined;

        return $this;
    }

    public function getRdrStatus(): int
    {
        return $this->rdrStatus;
    }

    public function setRdrStatus(int $rdrStatus): self
    {
        $this->rdrStatus = $rdrStatus;

        return $this;
    }

    public function getIncentiveOccurrence(): ?string
    {
        return $this->incentiveOccurrence;
    }

    public function setIncentiveOccurrence(?string $incentiveOccurrence): self
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

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): self
    {
        $this->userEmail = $userEmail;

        return $this;
    }
}
