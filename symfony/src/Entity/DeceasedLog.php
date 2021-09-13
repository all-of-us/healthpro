<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeceasedLogRepository")
 * @ORM\Table(uniqueConstraints={
 *   @ORM\UniqueConstraint(
 *     name="deceased_log_unique",
 *     columns={"participant_id", "organization_id", "deceased_status"})
 *   })
 */
class DeceasedLog
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
    private $deceasedTs;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $organizationId;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     */
    private $emailNotified;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $deceasedStatus;

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

    public function getDeceasedTs(): ?\DateTimeInterface
    {
        return $this->deceasedTs;
    }

    public function setDeceasedTs(?\DateTimeInterface $deceasedTs): self
    {
        $this->deceasedTs = $deceasedTs;

        return $this;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(?string $organizationId): self
    {
        $this->organizationId = $organizationId;

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

    public function getDeceasedStatus(): ?string
    {
        return $this->deceasedStatus;
    }

    public function setDeceasedStatus(?string $deceasedStatus): self
    {
        $this->deceasedStatus = $deceasedStatus;

        return $this;
    }
}
