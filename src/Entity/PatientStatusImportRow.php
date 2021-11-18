<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PatientStatusImportRowRepository")
 * @ORM\Table(name="patient_status_import_rows", indexes={
 *   @ORM\Index(name="import_id", columns={"import_id"})
 * })
 */
class PatientStatusImportRow
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
     * @ORM\Column(type="string", length=50)
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comments;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\PatientStatusImport", inversedBy="PatientStatusImportRows")
     * @ORM\JoinColumn(nullable=false)
     */
    private $import;

    /**
     * @ORM\Column(type="smallint", options={"default":0})
     */
    private $rdrStatus = 0;

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

    public function getRdrStatus(): ?int
    {
        return $this->rdrStatus;
    }

    public function setRdrStatus(?int $rdrStatus): self
    {
        $this->rdrStatus = $rdrStatus;

        return $this;
    }
}
