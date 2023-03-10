<?php

namespace App\Helper;

use App\Util;
use App\Drc\CodeBook;

/**
 * Define magic properties to fix phpstan errors
 * @property string $streetAddress
 * @property string $city
 * @property string $state
 * @property string $zipCode
 * @property string $email
 * @property string $lastName
 * @property string $firstName
 * @property string $phoneNumber
 */

class Participant
{
    public $status = true;
    public $statusReason;
    public $id;
    public $gender;
    public $dob;
    public $cacheTime;
    public $rdrData;
    public $evaluationFinalizedSite;
    public $orderCreatedSite;
    public $age;
    public $patientStatus;
    public $isCoreParticipant = false;
    public $isCoreMinusPMParticipant = false;
    public $activityStatus;
    public $isSuspended = false;
    public $isWithdrawn = false;
    public $consentCohortText;
    public $editExistingOnly = false;
    public $siteSuffix;
    public $enrollmentSiteSuffix;
    public $participantIncentiveDateGiven;
    public $isAIAN;

    private $disableTestAccess;
    private $cohortOneLaunchTime;
    private $siteType;

    private static $consentCohortValues = [
        'COHORT_1' => 'Cohort 1',
        'COHORT_2' => 'Cohort 2',
        'COHORT_2_PILOT' => 'Cohort 2 Pilot',
        'COHORT_3' => 'Cohort 3'
    ];

    private static $withdrawalStatusValues = [
        'NO_USE',
        'EARLY_OUT'
    ];

    private static $deceasedStatusValues = [
        'PENDING',
        'APPROVED'
    ];

    public function __construct($rdrParticipant = null)
    {
        if (is_object($rdrParticipant)) {
            if (!empty($rdrParticipant->cacheTime)) {
                $this->cacheTime = $rdrParticipant->cacheTime;
                unset($rdrParticipant->cacheTime);
            }
            if (isset($rdrParticipant->options)) {
                $this->disableTestAccess = $rdrParticipant->options['disableTestAccess'];
                $this->cohortOneLaunchTime = $rdrParticipant->options['cohortOneLaunchTime'];
                $this->siteType = $rdrParticipant->options['siteType'];
                unset($rdrParticipant->options);
            }
            $this->rdrData = $rdrParticipant;
            $this->parseRdrParticipant($rdrParticipant);
        }
    }

    private function parseRdrParticipant($participant)
    {
        if (!is_object($participant)) {
            return;
        }

        // Use participant id as id
        if (isset($participant->participantId)) {
            $this->id = $participant->participantId;
        }

        // Check for participants associated with TEST organization when disableTestAccess is set to true
        if (!empty($this->disableTestAccess) && $participant->hpoId === 'TEST') {
            $this->status = false;
            $this->statusReason = 'test-participant';
        }
        // HealthPro status is active if participant is consented, has completed basics survey, and is not withdrawn
        if (empty($participant->questionnaireOnTheBasics) || $participant->questionnaireOnTheBasics !== 'SUBMITTED') {
            $this->status = false;
            $this->statusReason = 'basics';
        }
        if (isset($participant->consentCohort) && $participant->consentCohort === 'COHORT_3') {
            if (isset($participant->consentForGenomicsROR) && $participant->consentForGenomicsROR === 'UNSET') {
                $this->status = false;
                $this->editExistingOnly = true;
                $this->statusReason = 'genomics';
            }
            if (isset($this->siteType) && isset($participant->consentForElectronicHealthRecords) && $this->siteType === 'hpo' && !in_array($participant->consentForElectronicHealthRecords, ['SUBMITTED', 'SUBMITTED_INVALID', 'SUBMITTED_NOT_VALIDATED'])) {
                $this->status = false;
                $this->editExistingOnly = true;
                $this->statusReason = 'ehr-consent';
            }
        }
        if (isset($participant->consentCohort) && $participant->consentCohort === 'COHORT_2') {
            if (isset($participant->clinicPhysicalMeasurementsStatus) && isset($participant->samplesToIsolateDNA) && ($participant->clinicPhysicalMeasurementsStatus !== 'COMPLETED' || $participant->samplesToIsolateDNA !== 'RECEIVED')) {
                if (isset($participant->consentForGenomicsROR) && $participant->consentForGenomicsROR === 'UNSET') {
                    $this->status = false;
                    $this->editExistingOnly = true;
                    $this->statusReason = 'genomics';
                }
                if (isset($participant->questionnaireOnDnaProgram) && $participant->questionnaireOnDnaProgram !== 'SUBMITTED') {
                    $this->status = false;
                    $this->editExistingOnly = true;
                    $this->statusReason = 'program-update';
                }
            }
        }

        if (isset($participant->consentCohort) && $participant->consentCohort === 'COHORT_1') {
            if (isset($participant->clinicPhysicalMeasurementsStatus) && isset($participant->samplesToIsolateDNA) && ($participant->clinicPhysicalMeasurementsStatus !== 'COMPLETED' || $participant->samplesToIsolateDNA !== 'RECEIVED')) {
                if (isset($participant->consentForGenomicsROR) && $participant->consentForGenomicsROR === 'UNSET') {
                    $this->status = false;
                    $this->editExistingOnly = true;
                    $this->statusReason = 'genomics';
                }
                if (isset($participant->consentForStudyEnrollmentAuthored) && !empty($this->cohortOneLaunchTime) && $participant->consentForStudyEnrollmentAuthored <= $this->cohortOneLaunchTime) {
                    $this->status = false;
                    $this->editExistingOnly = true;
                    $this->statusReason = 'primary-consent-update';
                }
            }
        }

        // Deceased Participant
        if (isset($participant->deceasedStatus)) {
            if ($participant->deceasedStatus === 'PENDING') {
                $this->status = false;
                $this->statusReason = 'deceased-pending';
                $this->editExistingOnly = true;
            }
            if ($participant->deceasedStatus === 'APPROVED') {
                $this->status = false;
                $this->statusReason = 'deceased-approved';
                $this->editExistingOnly = true;
            }
        }

        if (!empty($participant->withdrawalStatus) && in_array($participant->withdrawalStatus, self::$withdrawalStatusValues, true)) {
            $this->status = false;
            $this->statusReason = 'withdrawal';
            $this->isWithdrawn = true;
        }
        // RDR should not be returning participant data for unconsented participants, but adding this check to be safe
        // Participant details tab is disabled for the below two status reasons
        if (empty($participant->consentForStudyEnrollment) || $participant->consentForStudyEnrollment !== 'SUBMITTED') {
            $this->status = false;
            $this->statusReason = 'consent';
        }

        // Map gender identity to gender options for MayoLINK.
        switch (isset($participant->genderIdentity) ? $participant->genderIdentity : null) {
            case 'GenderIdentity_Woman':
                $this->gender = 'F';
                break;
            case 'GenderIdentity_Man':
                $this->gender = 'M';
                break;
            default:
                $this->gender = 'U';
                break;
        }

        // Set dob to DateTime object
        if (isset($participant->dateOfBirth)) {
            try {
                $this->dob = new \DateTime($participant->dateOfBirth);
            } catch (\Exception $e) {
                $this->dob = null;
            }
        }

        // Get site suffix
        if (!empty($participant->site) && $participant->site !== 'UNSET') {
            $this->siteSuffix = $this->getSiteSuffix($participant->site);
        }

        // Get enrollment site suffix
        if (!empty($participant->enrollmentSite) && $participant->enrollmentSite !== 'UNSET') {
            $this->enrollmentSiteSuffix = $this->getSiteSuffix($participant->enrollmentSite);
        }

        //Set age
        $this->age = $this->getAge();

        // Remove site prefix
        if (!empty($participant->clinicPhysicalMeasurementsFinalizedSite) && $participant->clinicPhysicalMeasurementsFinalizedSite !== 'UNSET') {
            $this->evaluationFinalizedSite = $this->getSiteSuffix($participant->clinicPhysicalMeasurementsFinalizedSite);
        }

        if (!empty($participant->biospecimenSourceSite) && $participant->biospecimenSourceSite !== 'UNSET') {
            $this->orderCreatedSite = $this->getSiteSuffix($participant->biospecimenSourceSite);
        }

        // Patient status
        if (isset($participant->patientStatus)) {
            $this->patientStatus = $participant->patientStatus;
        }

        // Determine core participant
        if (!empty($participant->enrollmentStatus) && $participant->enrollmentStatus === 'FULL_PARTICIPANT') {
            $this->isCoreParticipant = true;
        }

        // Determine core minus pm participant
        if (!empty($participant->enrollmentStatus) && $participant->enrollmentStatus === 'CORE_MINUS_PM') {
            $this->isCoreMinusPMParticipant = true;
        }

        // Set activity status
        if (isset($participant->withdrawalStatus)) {
            $this->activityStatus = $this->getActivityStatus($participant);
        }

        // Set suspension status
        if (isset($participant->suspensionStatus) && $participant->suspensionStatus === 'NO_CONTACT') {
            $this->isSuspended = true;
        }

        // Set consent cohort text
        if (isset($participant->consentCohort)) {
            $this->consentCohortText = $this->getConsentCohortText($participant);
        }

        // Set most recent participant incentive date given
        if (isset($participant->participantIncentives)) {
            $this->participantIncentiveDateGiven = $this->getParticipantIncentiveDateGiven($participant->participantIncentives);
        }

        if (isset($participant->aian)) {
            $this->isAIAN = $participant->aian;
        }
    }

    public function getShortId()
    {
        if (strlen($this->id) >= 36) {
            return strtoupper(Util::shortenUuid($this->id));
        } else {
            return $this->id;
        }
    }

    public function getMayolinkDob()
    {
        return new \DateTime('1933-03-03');
    }

    public function getAddress($multiline = false)
    {
        $address = '';
        if ($this->streetAddress) {
            $address .= $this->streetAddress;
            // Check if streetAddress2 is set, RDR doesn't return this field if it's empty
            if (!empty($this->streetAddress2)) {
                $address .= $multiline ? "\n" : ', ';
                $address .= $this->streetAddress2;
            }
            if ($this->city || $this->state || $this->zipCode) {
                $address .= $multiline ? "\n" : ', ';
            }
        }
        if ($this->city) {
            $address .= $this->city;
            $address .= $this->state ? ', ' : ' ';
        }
        if ($this->state) {
            $address .= $this->state . ' ';
        }
        if ($this->zipCode) {
            $address .= $this->zipCode;
        }
        return trim($address);
    }

    public function getAge()
    {
        if (!$this->dob) {
            return null;
        } else {
            return $this->dob
                ->diff(new \DateTime())
                ->y;
        }
    }

    /**
     * Magic methods for RDR data
     */
    public function __get($key)
    {
        if (isset($this->rdrData->{$key})) {
            return CodeBook::display($this->rdrData->{$key});
        } else {
            if (strpos($key, 'num') === 0) {
                return 0;
            } else {
                return null;
            }
        }
    }

    public function __isset($key)
    {
        return true;
    }

    public function checkIdentifiers($notes)
    {
        if (empty($notes)) {
            return false;
        }
        $identifiers = [];
        $dob = $this->dob;
        if ($dob) {
            $identifiers['dob'] = [
                $dob->format('m/d/y'),
                $dob->format('m-d-y'),
                $dob->format('m.d.y'),
                $dob->format('m/d/Y'),
                $dob->format('m-d-Y'),
                $dob->format('m.d.Y'),
                $dob->format('d/m/y'),
                $dob->format('d-m-y'),
                $dob->format('d.m.y'),
                $dob->format('d/m/Y'),
                $dob->format('d-m-Y'),
                $dob->format('d.m.Y'),
                $dob->format('n/j/y'),
                $dob->format('n-j-y'),
                $dob->format('n.j.y'),
                $dob->format('n/j/Y'),
                $dob->format('n-j-Y'),
                $dob->format('n.j.Y'),
                $dob->format('j/n/y'),
                $dob->format('j-n-y'),
                $dob->format('j.n.y'),
                $dob->format('j/n/Y'),
                $dob->format('j-n-Y'),
                $dob->format('j.n.Y')
            ];
        }
        if ($this->email) {
            $identifiers['email'] = [$this->email];
        }

        // Detect dob and email
        foreach ($identifiers as $key => $identifier) {
            foreach ($identifier as $value) {
                if (stripos($notes, $value) !== false) {
                    return [$key, $value];
                }
            }
        }

        // Detect name
        if ($this->firstName && $this->lastName) {
            $fName = preg_quote($this->firstName, '/');
            $lName = preg_quote($this->lastName, '/');
            if (preg_match("/(?:\W|^)({$fName}\W*{$lName}|{$lName}\W*{$fName})(?:\W|$)/i", $notes, $matches)) {
                return ['name', $matches[1]];
            }
        }

        // Detect address
        if ($this->streetAddress) {
            $address = preg_split('/[\s]/', $this->streetAddress);
            $address = array_map(function ($value) {
                return preg_quote($value, '/');
            }, $address);
            $pattern = '/(?:\W|^)';
            $pattern .= join('\W*', $address);
            $pattern .= '(?:\W|$)/i';

            if (preg_match($pattern, $notes, $matches)) {
                return ['address', $matches[0]];
            }
        }

        // Detect phone number
        $phone = preg_replace('/\D/', '', $this->phoneNumber);
        if ($phone) {
            $identifiers['phone'] = [$phone];
            if (strlen($phone) === 10) {
                $num1 = preg_quote(substr($phone, 0, 3));
                $num2 = preg_quote(substr($phone, 3, 3));
                $num3 = preg_quote(substr($phone, 6));
                if (preg_match("/(\W*{$num1}\W*{$num2}\W*{$num3})/i", $notes, $matches)) {
                    return ['phone', $matches[1]];
                }
            }
        }
        return false;
    }

    private function getSiteSuffix($site)
    {
        return str_replace(\App\Security\User::SITE_PREFIX, '', $site);
    }

    private function getActivityStatus($participant)
    {
        // Withdrawn
        if (in_array($participant->withdrawalStatus, self::$withdrawalStatusValues, true)) {
            return 'withdrawn';
        }

        // Deactivated
        if (isset($participant->suspensionStatus) && $participant->suspensionStatus === 'NO_CONTACT') {
            return 'deactivated';
        }

        // Deceased Status
        if (in_array($participant->deceasedStatus, self::$deceasedStatusValues, true)) {
            return 'deceased';
        }

        // Default
        return 'active';
    }

    private function getConsentCohortText($participant)
    {
        if ($participant->consentCohort === 'COHORT_2' && isset($participant->cohort2PilotFlag) && $participant->cohort2PilotFlag === 'COHORT_2_PILOT') {
            return self::$consentCohortValues[$participant->cohort2PilotFlag];
        } else {
            return self::$consentCohortValues[$participant->consentCohort] ?? $participant->consentCohort;
        }
    }

    private function getParticipantIncentiveDateGiven($participantIncentives): string
    {
        if ($participantIncentives && is_array($participantIncentives)) {
            $count = count($participantIncentives);
            $incentive = $participantIncentives[$count - 1];
            $incentiveDate = date_parse($incentive->dateGiven);
            return $incentiveDate['month'] . '/' . $incentiveDate['day'] . '/' .
                $incentiveDate['year'];
        }
        return '';
    }
}
