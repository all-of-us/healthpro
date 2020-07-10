<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PatientStatusTempRepository")
 */
class PatientStatusTemp
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
    private $participant_id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comments;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\PatientStatusImport", inversedBy="patientStatusTemps")
     * @ORM\JoinColumn(nullable=false)
     */
    private $import;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_ts;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantId(): ?string
    {
        return $this->participant_id;
    }

    public function setParticipantId(string $participant_id): self
    {
        $this->participant_id = $participant_id;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;

        return $this;
    }

    public function getImport(): ?PatientStatusImport
    {
        return $this->import;
    }

    public function setImport(?PatientStatusImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->created_ts;
    }

    public function setCreatedTs(\DateTimeInterface $created_ts): self
    {
        $this->created_ts = $created_ts;

        return $this;
    }
}
