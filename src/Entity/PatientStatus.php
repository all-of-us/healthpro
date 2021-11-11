<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PatientStatusRepository")
 * @ORM\Table(uniqueConstraints={
 *   @ORM\UniqueConstraint(
 *     name="participant_organization_unique",
 *     columns={"participant_id", "organization"})
 *   },
 *   indexes={
 *     @ORM\Index(name="history_id", columns={"history_id"})
 * })
 */
class PatientStatus
{
    public static $patientStatus = [
        'Yes: Confirmed in EHR system' => 'YES',
        'No: Not found in EHR system' => 'NO',
        'No Access: Unable to check EHR system' => 'NO_ACCESS',
        'Unknown: Inconclusive search results' => 'UNKNOWN'
    ];

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
    private $organization;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $awardee;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PatientStatusHistory", mappedBy="patientStatus")
     */
    private $patientStatusHistories;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\PatientStatusHistory", inversedBy="patientStatusRecords", cascade={"persist", "remove"})
     */
    private $history;

    public function __construct()
    {
        $this->patientStatusHistories = new ArrayCollection();
    }

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
            $patientStatusHistory->setPatientStatus($this);
        }

        return $this;
    }

    public function removePatientStatusHistory(PatientStatusHistory $patientStatusHistory): self
    {
        if ($this->patientStatusHistories->contains($patientStatusHistory)) {
            $this->patientStatusHistories->removeElement($patientStatusHistory);
            // set the owning side to null (unless already changed)
            if ($patientStatusHistory->getPatientStatus() === $this) {
                $patientStatusHistory->setPatientStatus(null);
            }
        }

        return $this;
    }

    public function getHistory(): ?PatientStatusHistory
    {
        return $this->history;
    }

    public function setHistory(?PatientStatusHistory $history): self
    {
        $this->history = $history;

        return $this;
    }
}
