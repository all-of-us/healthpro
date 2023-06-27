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
    private ?string $NphParticipant = null;

    #[ORM\Column(length: 10)]
    private ?string $module = null;

    #[ORM\Column(length: 50)]
    private ?string $visit = null;

    #[ORM\Column(length: 100)]
    private ?string $doseBatchId = null;

    #[ORM\Column]
    private ?float $actualDose = null;

    #[ORM\Column]
    private ?float $participantWeight = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $doseAdministered = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNphParticipant(): ?string
    {
        return $this->NphParticipant;
    }

    public function setNphParticipant(string $NphParticipant): static
    {
        $this->NphParticipant = $NphParticipant;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getVisit(): ?string
    {
        return $this->visit;
    }

    public function setVisit(string $visit): static
    {
        $this->visit = $visit;

        return $this;
    }

    public function getDoseBatchId(): ?string
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

    public function getParticipantWeight(): ?float
    {
        return $this->participantWeight;
    }

    public function setParticipantWeight(float $participantWeight): static
    {
        $this->participantWeight = $participantWeight;

        return $this;
    }

    public function getDoseAdministered(): ?\DateTimeInterface
    {
        return $this->doseAdministered;
    }

    public function setDoseAdministered(\DateTimeInterface $doseAdministered): static
    {
        $this->doseAdministered = $doseAdministered;

        return $this;
    }
}
