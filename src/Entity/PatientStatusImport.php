<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\PatientStatusImportRepository')]
class PatientStatusImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $fileName;

    #[ORM\Column(type: 'integer')]
    private $userId;

    #[ORM\Column(type: 'string', length: 50)]
    private $awardee;

    #[ORM\Column(type: 'string', length: 50)]
    private $site;

    #[ORM\Column(type: 'datetime')]
    private $createdTs;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private $importStatus = 0;

    #[ORM\OneToMany(targetEntity: PatientStatusImportRow::class, mappedBy: 'import', cascade: ['persist', 'remove'])]
    private $PatientStatusImportRows;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private $confirm = 0;

    #[ORM\OneToMany(targetEntity: 'App\Entity\PatientStatusHistory', mappedBy: 'import')]
    private $patientStatusHistories;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Organization')]
    #[ORM\JoinColumn(name: 'organization', referencedColumnName: 'id')]
    private $organization;

    public function __construct()
    {
        $this->PatientStatusImportRows = new ArrayCollection();
        $this->patientStatusHistories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getAwardee(): ?string
    {
        return $this->awardee;
    }

    public function setAwardee(string $awardee): self
    {
        $this->awardee = $awardee;

        return $this;
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

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getImportStatus(): ?int
    {
        return $this->importStatus;
    }

    public function setImportStatus(?int $importStatus): self
    {
        $this->importStatus = $importStatus;

        return $this;
    }

    /**
     * @return Collection|PatientStatusImportRow[]
     */
    public function getPatientStatusImportRows(): Collection
    {
        return $this->PatientStatusImportRows;
    }

    public function addPatientStatusImportRow(PatientStatusImportRow $PatientStatusImportRow): self
    {
        if (!$this->PatientStatusImportRows->contains($PatientStatusImportRow)) {
            $this->PatientStatusImportRows[] = $PatientStatusImportRow;
            $PatientStatusImportRow->setImport($this);
        }

        return $this;
    }

    public function removePatientStatusImportRow(PatientStatusImportRow $PatientStatusImportRow): self
    {
        if ($this->PatientStatusImportRows->contains($PatientStatusImportRow)) {
            $this->PatientStatusImportRows->removeElement($PatientStatusImportRow);
            // set the owning side to null (unless already changed)
            if ($PatientStatusImportRow->getImport()->getId() === $this->getId()) {
                $PatientStatusImportRow->setImport(null);
            }
        }

        return $this;
    }

    public function getConfirm(): ?int
    {
        return $this->confirm;
    }

    public function setConfirm(int $confirm): self
    {
        $this->confirm = $confirm;

        return $this;
    }

    /**
     * @return Collection|PatientStatusHistory[]
     */
    public function getPatientStatusHistories(): Collection
    {
        return $this->patientStatusHistories;
    }

    public function addPatientStatusHistory(PatientStatusHistory $patientStatusHistory): self
    {
        if (!$this->patientStatusHistories->contains($patientStatusHistory)) {
            $this->patientStatusHistories[] = $patientStatusHistory;
            $patientStatusHistory->setImport($this);
        }

        return $this;
    }

    public function removePatientStatusHistory(PatientStatusHistory $patientStatusHistory): self
    {
        if ($this->patientStatusHistories->contains($patientStatusHistory)) {
            $this->patientStatusHistories->removeElement($patientStatusHistory);
            // set the owning side to null (unless already changed)
            if ($patientStatusHistory->getImport() === $this) {
                $patientStatusHistory->setImport(null);
            }
        }

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }
}
