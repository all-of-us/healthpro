<?php

namespace App\Entity;

use App\Repository\IdVerificationImportRowRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IdVerificationImportRowRepository::class)
 */
class IdVerificationImportRow
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\ManyToOne(targetEntity=IncentiveImport::class, inversedBy="idVerificationImportRows")
     * @ORM\JoinColumn(nullable=false)
     */
    private $import;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $user_email;

    /**
     * @ORM\Column(type="datetime")
     */
    private $verifiedDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $verificationType;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $visitType;

    /**
     * @ORM\Column(type="smallint")
     */
    private $rdrStatus;

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

    public function setImport(?IncentiveImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->user_email;
    }

    public function setUserEmail(?string $user_email): self
    {
        $this->user_email = $user_email;

        return $this;
    }

    public function getVerifiedDate(): ?\DateTimeInterface
    {
        return $this->verifiedDate;
    }

    public function setVerifiedDate(\DateTimeInterface $verifiedDate): self
    {
        $this->verifiedDate = $verifiedDate;

        return $this;
    }

    public function getVerificationType(): ?string
    {
        return $this->verificationType;
    }

    public function setVerificationType(?string $verificationType): self
    {
        $this->verificationType = $verificationType;

        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(?string $visitType): self
    {
        $this->visitType = $visitType;

        return $this;
    }

    public function getRdrStatus(): ?int
    {
        return $this->rdrStatus;
    }

    public function setRdrStatus(int $rdrStatus): self
    {
        $this->rdrStatus = $rdrStatus;

        return $this;
    }
}
