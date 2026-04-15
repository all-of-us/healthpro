<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\IdVerificationImportRowRepository')]
class IdVerificationImportRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $participantId;

    #[ORM\ManyToOne(targetEntity: IdVerificationImport::class, inversedBy: 'idVerificationImportRows')]
    #[ORM\JoinColumn(nullable: false)]
    private IdVerificationImport $import;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $verifiedDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $verificationType = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $visitType = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $rdrStatus = 0;

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

    public function getImport(): ?IdVerificationImport
    {
        return $this->import;
    }

    public function setImport(IdVerificationImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): self
    {
        $this->userEmail = $userEmail;

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
