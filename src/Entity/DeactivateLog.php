<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeactivateLogRepository")
 * @ORM\Table(uniqueConstraints={
 *   @ORM\UniqueConstraint(
 *     name="participant_id",
 *     columns={"participant_id", "deactivate_ts"})
 *   },
 *   indexes={
 *     @ORM\Index(name="hpo_id", columns={"hpo_id"})
 *   })
 */
class DeactivateLog
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $insertTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $deactivateTs;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $hpoId;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     */
    private $emailNotified;

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

    public function getInsertTs(): ?\DateTimeInterface
    {
        return $this->insertTs;
    }

    public function setInsertTs(\DateTimeInterface $insertTs): self
    {
        $this->insertTs = $insertTs;

        return $this;
    }

    public function getDeactivateTs(): ?\DateTimeInterface
    {
        return $this->deactivateTs;
    }

    public function setDeactivateTs(?\DateTimeInterface $deactivateTs): self
    {
        $this->deactivateTs = $deactivateTs;

        return $this;
    }

    public function getHpoId(): ?string
    {
        return $this->hpoId;
    }

    public function setHpoId(?string $hpoId): self
    {
        $this->hpoId = $hpoId;

        return $this;
    }

    public function getEmailNotified(): ?string
    {
        return $this->emailNotified;
    }

    public function setEmailNotified(?string $emailNotified): self
    {
        $this->emailNotified = $emailNotified;

        return $this;
    }
}
