<?php

namespace App\Entity;

use App\Repository\NphDlwRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NphDlwRepository::class)]
class NphDlw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $NphParticipant = '';

    #[ORM\Column(length: 10)]
    private string $module = '';

    #[ORM\Column(length: 50)]
    private string $visit = '';

    #[ORM\Column(length: 100)]
    private string $doseBatchId = '';

    #[ORM\Column]
    private float $actualDose = 0.0;

    #[ORM\Column]
    private float $participantWeight = 0.0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $doseAdministered;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modifiedTs = null;

    #[ORM\Column(nullable: true)]
    private ?int $ModifiedTimezoneId = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getNphParticipant(): string
    {
        return $this->NphParticipant;
    }

    public function setNphParticipant(string $NphParticipant): static
    {
        $this->NphParticipant = $NphParticipant;

        return $this;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getVisit(): string
    {
        return $this->visit;
    }

    public function setVisit(string $visit): static
    {
        $this->visit = $visit;

        return $this;
    }

    public function getDoseBatchId(): string
    {
        return $this->doseBatchId;
    }

    public function setDoseBatchId(string $doseBatchId): static
    {
        $this->doseBatchId = $doseBatchId;

        return $this;
    }

    public function getActualDose(): ?float
    {
        return $this->actualDose;
    }

    public function setActualDose(float $actualDose): static
    {
        $this->actualDose = $actualDose;

        return $this;
    }

    public function getParticipantWeight(): float
    {
        return $this->participantWeight;
    }

    public function setParticipantWeight(float $participantWeight): static
    {
        $this->participantWeight = $participantWeight;

        return $this;
    }

    public function getDoseAdministered(): \DateTimeInterface
    {
        return $this->doseAdministered;
    }

    public function setDoseAdministered(\DateTimeInterface $doseAdministered): static
    {
        $this->doseAdministered = $doseAdministered;

        return $this;
    }

    public function getModifiedTs(): ?\DateTime
    {
        return $this->modifiedTs;
    }

    public function setModifiedTs(?\DateTime $modifiedTs): self
    {
        $this->modifiedTs = $modifiedTs;

        return $this;
    }

    public function getModifiedTimezoneId(): ?int
    {
        return $this->ModifiedTimezoneId;
    }

    public function setModifiedTimezoneId(?int $ModifiedTimezoneId): static
    {
        $this->ModifiedTimezoneId = $ModifiedTimezoneId;

        return $this;
    }
}
