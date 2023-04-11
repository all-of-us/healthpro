<?php

namespace App\Entity;

use App\Repository\DlwRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DlwRepository::class)
 */
class Dlw
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     */
    private $doseBatchId;

    /**
     * @ORM\Column(type="integer")
     */
    private $nphSampleId;

    /**
     * @ORM\Column(type="float")
     */
    private $actualDose;

    /**
     * @ORM\Column(type="float")
     */
    private $participantWeight;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDoseBatchId(): ?string
    {
        return $this->doseBatchId;
    }

    public function setDoseBatchId(string $doseBatchId): self
    {
        $this->doseBatchId = $doseBatchId;

        return $this;
    }

    public function getNphSampleId(): ?int
    {
        return $this->nphSampleId;
    }

    public function setNphSampleId(int $nphSampleId): self
    {
        $this->nphSampleId = $nphSampleId;

        return $this;
    }

    public function getActualDose(): ?float
    {
        return $this->actualDose;
    }

    public function setActualDose(float $actualDose): self
    {
        $this->actualDose = $actualDose;

        return $this;
    }

    public function getParticipantWeight(): ?float
    {
        return $this->participantWeight;
    }

    public function setParticipantWeight(float $participantWeight): self
    {
        $this->participantWeight = $participantWeight;

        return $this;
    }
}
