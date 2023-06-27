<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Index(columns: ['hpo_id'], name: 'hpo_id')]
#[ORM\Index(columns: ['participant_id'], name: 'participant_id')]
#[ORM\Entity(repositoryClass: 'App\Repository\WithdrawalLogRepository')]
class WithdrawalLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $participantId;

    #[ORM\Column(type: 'datetime')]
    private $insertTs;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $withdrawalTs;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $hpoId;

    #[ORM\Column(type: 'string', length: 2000, nullable: true)]
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

    public function getWithdrawalTs(): ?\DateTimeInterface
    {
        return $this->withdrawalTs;
    }

    public function setWithdrawalTs(\DateTimeInterface $withdrawalTs): self
    {
        $this->withdrawalTs = $withdrawalTs;

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
