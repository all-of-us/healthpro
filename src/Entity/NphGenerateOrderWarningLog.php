<?php

namespace App\Entity;

use App\Repository\NphGenerateOrderWarningLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NphGenerateOrderWarningLogRepository::class)]
class NphGenerateOrderWarningLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $module;

    #[ORM\Column(length: 10)]
    private string $period;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $site;

    #[ORM\Column(length: 50)]
    private string $participantId;

    #[ORM\Column(length: 50)]
    private string $biobankId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $modifiedTs;

    #[ORM\Column(nullable: true)]
    private ?int $modifiedTimezoneId = null;

    #[ORM\Column(nullable: true)]
    private ?int $IncompleteSamples = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSite(): string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getParticipantId(): string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): static
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getBiobankId(): string
    {
        return $this->biobankId;
    }

    public function setBiobankId(string $biobankId): static
    {
        $this->biobankId = $biobankId;

        return $this;
    }

    public function getModifiedTs(): \DateTimeInterface
    {
        return $this->modifiedTs;
    }

    public function setModifiedTs(\DateTimeInterface $modifiedTs): static
    {
        $this->modifiedTs = $modifiedTs;

        return $this;
    }

    public function getModifiedTimezoneId(): ?int
    {
        return $this->modifiedTimezoneId;
    }

    public function setModifiedTimezoneId(?int $modifiedTimezoneId): static
    {
        $this->modifiedTimezoneId = $modifiedTimezoneId;

        return $this;
    }

    public function getIncompleteSamples(): ?int
    {
        return $this->IncompleteSamples;
    }

    public function setIncompleteSamples(?int $IncompleteSamples): static
    {
        $this->IncompleteSamples = $IncompleteSamples;

        return $this;
    }
}
