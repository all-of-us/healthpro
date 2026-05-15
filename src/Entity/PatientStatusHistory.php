<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Index(name: 'patient_status_id', columns: ['patient_status_id'])]
#[ORM\Index(name: 'import_id', columns: ['import_id'])]
#[ORM\Entity(repositoryClass: 'App\Repository\PatientStatusHistoryRepository')]
class PatientStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $site;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $rdrTs = null;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\PatientStatus', inversedBy: 'patientStatusHistories')]
    #[ORM\JoinColumn(nullable: false)]
    private PatientStatus $patientStatus;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\PatientStatusImport', inversedBy: 'patientStatusHistories')]
    private ?PatientStatusImport $import = null;

    #[ORM\OneToOne(targetEntity: 'App\Entity\PatientStatus', mappedBy: 'history', cascade: ['persist', 'remove'])]
    private ?PatientStatus $patientStatusRecords = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
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

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getRdrTs(): ?\DateTimeInterface
    {
        return $this->rdrTs;
    }

    public function setRdrTs(?\DateTimeInterface $rdrTs): self
    {
        $this->rdrTs = $rdrTs;

        return $this;
    }

    public function getPatientStatus(): ?PatientStatus
    {
        return $this->patientStatus;
    }

    public function setPatientStatus(PatientStatus $patientStatus): self
    {
        $this->patientStatus = $patientStatus;

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

    public function getPatientStatusRecords(): ?PatientStatus
    {
        return $this->patientStatusRecords;
    }

    public function setPatientStatusRecords(?PatientStatus $patientStatusRecords): self
    {
        $this->patientStatusRecords = $patientStatusRecords;

        // set (or unset) the owning side of the relation if necessary
        $newHistory = null === $patientStatusRecords ? null : $this;
        if ($patientStatusRecords !== null && $patientStatusRecords->getHistory() !== $newHistory) {
            $patientStatusRecords->setHistory($newHistory);
        }

        return $this;
    }
}
