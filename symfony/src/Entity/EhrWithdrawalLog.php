<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EhrWithdrawalLogRepository")
 */
class EhrWithdrawalLog
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
    private $ehrWithdrawalTs;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $awardeeId;

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

    public function getEhrWithdrawalTs(): ?\DateTimeInterface
    {
        return $this->ehrWithdrawalTs;
    }

    public function setEhrWithdrawalTs(?\DateTimeInterface $ehrWithdrawalTs): self
    {
        $this->ehrWithdrawalTs = $ehrWithdrawalTs;

        return $this;
    }

    public function getAwardeeId(): ?string
    {
        return $this->awardeeId;
    }

    public function setAwardeeId(?string $awardeeId): self
    {
        $this->awardeeId = $awardeeId;

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
