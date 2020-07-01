<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PatientStatusImportRepository")
 */
class PatientStatusImport
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $file_name;

    /**
     * @ORM\Column(type="integer")
     */
    private $user_id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $organization;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $awardee;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_ts;

    /**
     * @ORM\Column(type="smallint")
     */
    private $import_status;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PatientStatusTemp", mappedBy="import")
     */
    private $patientStatusTemps;

    /**
     * @ORM\Column(type="smallint")
     */
    private $confirm;

    public function __construct()
    {
        $this->patientStatusTemps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    public function setFileName(string $file_name): self
    {
        $this->file_name = $file_name;

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(string $organization): self
    {
        $this->organization = $organization;

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
        return $this->user_id;
    }

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;

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
        return $this->created_ts;
    }

    public function setCreatedTs(\DateTimeInterface $created_ts): self
    {
        $this->created_ts = $created_ts;

        return $this;
    }

    public function getImportStatus(): ?int
    {
        return $this->import_status;
    }

    public function setImportStatus(?int $import_status): self
    {
        $this->import_status = $import_status;

        return $this;
    }

    /**
     * @return Collection|PatientStatusTemp[]
     */
    public function getPatientStatusTemps(): Collection
    {
        return $this->patientStatusTemps;
    }

    public function addPatientStatusTemp(PatientStatusTemp $patientStatusTemp): self
    {
        if (!$this->patientStatusTemps->contains($patientStatusTemp)) {
            $this->patientStatusTemps[] = $patientStatusTemp;
            $patientStatusTemp->setImportId($this);
        }

        return $this;
    }

    public function removePatientStatusTemp(PatientStatusTemp $patientStatusTemp): self
    {
        if ($this->patientStatusTemps->contains($patientStatusTemp)) {
            $this->patientStatusTemps->removeElement($patientStatusTemp);
            // set the owning side to null (unless already changed)
            if ($patientStatusTemp->getImportId() === $this) {
                $patientStatusTemp->setImportId(null);
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
}
