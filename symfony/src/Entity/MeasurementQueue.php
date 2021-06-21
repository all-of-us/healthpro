<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="evaluations_queue")
 * @ORM\Entity(repositoryClass="App\Repository\MeasurementQueueRepository")
 */
class MeasurementQueue
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $evaluationId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $evaluationParentId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $oldRdrId;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $newRdrId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $fhirVersion;

    /**
     * @ORM\Column(type="datetime")
     */
    private $queuedTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $sentTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $attemptedTs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvaluationId(): ?int
    {
        return $this->evaluationId;
    }

    public function setEvaluationId(int $evaluationId): self
    {
        $this->evaluationId = $evaluationId;

        return $this;
    }

    public function getEvaluationParentId(): ?int
    {
        return $this->evaluationParentId;
    }

    public function setEvaluationParentId(?int $evaluationParentId): self
    {
        $this->evaluationParentId = $evaluationParentId;

        return $this;
    }

    public function getOldRdrId(): ?string
    {
        return $this->oldRdrId;
    }

    public function setOldRdrId(string $oldRdrId): self
    {
        $this->oldRdrId = $oldRdrId;

        return $this;
    }

    public function getNewRdrId(): ?string
    {
        return $this->newRdrId;
    }

    public function setNewRdrId(?string $newRdrId): self
    {
        $this->newRdrId = $newRdrId;

        return $this;
    }

    public function getFhirVersion(): ?int
    {
        return $this->fhirVersion;
    }

    public function setFhirVersion(?int $fhirVersion): self
    {
        $this->fhirVersion = $fhirVersion;

        return $this;
    }

    public function getQueuedTs(): ?\DateTimeInterface
    {
        return $this->queuedTs;
    }

    public function setQueuedTs(\DateTimeInterface $queuedTs): self
    {
        $this->queuedTs = $queuedTs;

        return $this;
    }

    public function getSentTs(): ?\DateTimeInterface
    {
        return $this->sentTs;
    }

    public function setSentTs(?\DateTimeInterface $sentTs): self
    {
        $this->sentTs = $sentTs;

        return $this;
    }

    public function getAttemptedTs(): ?\DateTimeInterface
    {
        return $this->attemptedTs;
    }

    public function setAttemptedTs(?\DateTimeInterface $attemptedTs): self
    {
        $this->attemptedTs = $attemptedTs;

        return $this;
    }
}
