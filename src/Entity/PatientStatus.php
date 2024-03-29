<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Index(name: 'history_id', columns: ['history_id'])]
#[ORM\UniqueConstraint(name: 'participant_organization_unique', columns: ['participant_id', 'organization'])]
#[ORM\Entity(repositoryClass: 'App\Repository\PatientStatusRepository')]
class PatientStatus
{
    public const YES = 'YES';
    public const NO = 'NO';
    public const NO_ACCESS = 'NO_ACCESS';
    public const UNKNOWN = 'UNKNOWN';

    public static $patientStatus = [
        'Yes: Confirmed in EHR system' => self::YES,
        'No: Not found in EHR system' => self::NO,
        'No Access: Unable to check EHR system' => self::NO_ACCESS,
        'Unknown: Inconclusive search results' => self::UNKNOWN
    ];

    public static $onSitePatientStatus = [
        'Yes' => self::YES,
        'No' => self::NO,
        'No Access' => self::NO_ACCESS,
        'Unknown' => self::UNKNOWN
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $participantId;

    #[ORM\Column(type: 'string', length: 50)]
    private $organization;

    #[ORM\Column(type: 'string', length: 50)]
    private $awardee;

    #[ORM\OneToMany(targetEntity: 'App\Entity\PatientStatusHistory', mappedBy: 'patientStatus')]
    private $patientStatusHistories;

    #[ORM\OneToOne(targetEntity: 'App\Entity\PatientStatusHistory', inversedBy: 'patientStatusRecords', cascade: ['persist', 'remove'])]
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
