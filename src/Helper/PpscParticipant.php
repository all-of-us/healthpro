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
    public string|null $site;
    public string|null $dob;
    public string|null $organization;
    public string|null $isPediatric;
    public string|null $genderIdentity;
    public string|null $middleName;
    public string|null $lastName;
    public string|null $firstName;
    public string|null $biobankId;
    public string|null $enablePediatricEnrollment;

    public function __construct(?\stdClass $ppscParticipant = null)
    {
        if (is_object($ppscParticipant)) {
            $this->parsePPscParticipant($ppscParticipant);
        }
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
        $this->site = $participant->site ?? null;
        $this->dob = $participant->dob ?? null;
        $this->organization = $participant->organization ?? null;
        $this->isPediatric = $participant->isPediatric ?? null;
        $this->genderIdentity = $participant->GenderIdentity ?? null;
        $this->middleName = $participant->MiddleName ?? null;
        $this->lastName = $participant->LastName ?? null;
        $this->firstName = $participant->FirstName ?? null;
        $this->biobankId = $participant->BioBank_ID__c ?? null;
        $this->enablePediatricEnrollment = $participant->Enable_Pediatric_Enrollment__c ?? null;
    }
}
