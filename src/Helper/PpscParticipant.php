<?php

namespace App\Helper;

class PpscParticipant
{
    public string $id;
    public string|null $ageRange;
    public string|null $race;
    public string|null $sex;
    public string|null $deceasedStatus;
    public string|null $biospecimenSourceSite;
    public \DateTime|null $dob;
    public string|null $awardee;
    public string|null $organization;
    public string|null $site;
    public string|null $isPediatric;
    public string|null $genderIdentity;
    public string|null $middleName;
    public string|null $lastName;
    public string|null $firstName;
    public string|null $biobankId;
    public string|null $enablePediatricEnrollment;
    public string|null $pediatricMeasurementsVersionType;
    public int|null $age;
    public int|null $ageInMonths;
    public int|null $sexAtBirth;

    private static array $pediatricAgeRangeMeasurementVersions = [
        'peds-1' => [0, 23],
        'peds-2' => [24, 35],
        'peds-3' => [36, 59],
        'peds-4' => [60, 83],
    ];

    public function __construct(?\stdClass $ppscParticipant = null)
    {
        if (is_object($ppscParticipant)) {
            $this->parsePPscParticipant($ppscParticipant);
        }
    }

    public function getAge(): int|null
    {
        if (!$this->dob) {
            return null;
        }
        return $this->dob
            ->diff(new \DateTime())
            ->y;
    }

    private function parsePPscParticipant(\stdClass $participant): void
    {
        if (!is_object($participant)) {
            return;
        }
        $this->id = $participant->Participant_ID__c ?? '';
        $this->ageRange = $participant->ageRange ?? null;
        $this->race = $participant->race ?? null;
        $this->sex = $participant->sex ?? null;
        $this->deceasedStatus = $participant->deceasedStatus ?? null;
        $this->biospecimenSourceSite = $participant->biospecimenSourceSite ?? null;
        $this->dob = $participant->dob ?? null;
        $this->awardee = $participant->awardee ?? null;
        $this->organization = $participant->organization ?? null;
        $this->site = $participant->site ?? null;
        $this->isPediatric = $participant->isPediatric ?? null;
        $this->genderIdentity = $participant->GenderIdentity ?? null;
        $this->middleName = $participant->MiddleName ?? null;
        $this->lastName = $participant->LastName ?? null;
        $this->firstName = $participant->FirstName ?? null;
        $this->biobankId = $participant->BioBank_ID__c ?? null;
        $this->enablePediatricEnrollment = $participant->Enable_Pediatric_Enrollment__c ?? null;
        // Set dob to DateTime object
        if (isset($participant->dob)) {
            try {
                $this->dob = new \DateTime($participant->dob);
            } catch (\Exception $e) {
                $this->dob = null;
            }
        }
        $this->age = $this->getAge();
        $this->ageInMonths = $this->getAgeInMonths();
        $this->sexAtBirth = match ($participant->sex ?? null) {
            'SexAtBirth_Male' => 1,
            'SexAtBirth_Female' => 2,
            default => 0,
        };
        if ($this->isPediatric) {
            $measurementVersionType = '';
            foreach (self::$pediatricAgeRangeMeasurementVersions as $key => $range) {
                list($start, $end) = $range;
                if ($this->getAgeInMonths() >= $start && $this->getAgeInMonths() <= $end) {
                    $measurementVersionType = $key;
                    break;
                }
            }
            $this->pediatricMeasurementsVersionType = $measurementVersionType;
        }
    }

    private function getAgeInMonths(): int|null
    {
        if (!$this->dob) {
            return null;
        }
        $now = new \DateTime();
        $diff = $now->diff($this->dob);
        $yearsInMonths = $diff->y * 12;
        $months = $diff->m;
        return $yearsInMonths + $months;
    }
}
