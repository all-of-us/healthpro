<?php

namespace App\Helper;

use DateTime;

/**
 * Define magic properties to fix phpstan errors
 * @property string $email
 * @property string $lastName
 * @property string $firstName
 * @property string $phoneNumber
 */
class NphParticipant
{
    public const MODULE1_CONSENT_TISSUE = 'm1_consent_tissue';
    public const MODULE1_CONSENT_RECONTACT = 'm1_consent_gps';
    public const OPTIN_PERMIT = 'PERMIT';
    public const OPTIN_HAIR = 'PERMIT2';
    public const OPTIN_NAIL = 'PERMIT3';
    public const OPTIN_DENY = 'DENY';
    public const DIET_STARTED = 'started';
    public const DIET_COMPLETED = 'completed';
    public const DIET_DISCONTINUED = 'discontinued';
    public const DIET_CONTINUED = 'continued';
    public const DIET_INCOMPLETE = 'incomplete';
    public $id;
    public $cacheTime;
    public $rdrData;
    public $dob;
    public $nphPairedSiteSuffix;
    public $module;
    public $module1TissueConsentStatus;
    public $module1TissueConsentTime;
    public array $module1DietStatus;
    public array $module2DietStatus;
    public array $module3DietStatus;
    public array $module1DietPeriod;
    public array $module2DietPeriod;
    public array $module3DietPeriod;
    public string $biobankId = '';
    public array $moduleConsents = [];

    public function __construct(?\stdClass $rdrParticipant = null)
    {
        if (is_object($rdrParticipant)) {
            if (!empty($rdrParticipant->cacheTime)) {
                $this->cacheTime = $rdrParticipant->cacheTime;
                unset($rdrParticipant->cacheTime);
            }
            $this->rdrData = $rdrParticipant;
            $this->parseRdrParticipant($rdrParticipant);
        }
    }

    /**
     * Magic methods for RDR data
     */
    public function __get(string $key)
    {
        if (isset($this->rdrData->{$key})) {
            return $this->rdrData->{$key};
        }
        return null;
    }

    public function __isset(string $key)
    {
        return true;
    }

    private function getModule1TissueConsentStatus(): array
    {
        $latestDate = null;
        $consentStatus = [];
        if (isset($this->rdrData->nphModule1ConsentStatus) && is_array($this->rdrData->nphModule1ConsentStatus)) {
            foreach ($this->rdrData->nphModule1ConsentStatus as $consent) {
                if ($consent->value === self::MODULE1_CONSENT_TISSUE) {
                    $consentDate = new \DateTime($consent->time);
                    if ($latestDate === null || $consentDate > $latestDate) {
                        $latestDate = $consentDate;
                        $consentStatus['time'] = $consent->time;
                        $consentStatus['value'] = $consent->optIn;
                    }
                }
            }
        }
        $consentStatus['value'] = $consentStatus['value'] ?? self::OPTIN_DENY;
        $consentStatus['time'] = $consentStatus['time'] ?? null;
        return $consentStatus;
    }

    private function parseRdrParticipant(\stdClass $participant): void
    {
        if (!is_object($participant)) {
            return;
        }
        // Use nph participant id as id
        if (isset($participant->participantNphId)) {
            $this->id = $participant->participantNphId;
        }
        // Set dob to DateTime object
        if (isset($participant->nphDateOfBirth)) {
            try {
                $this->dob = new \DateTime($participant->nphDateOfBirth);
            } catch (\Exception $e) {
                $this->dob = null;
            }
        }
        // Get NPH site suffix
        if (!empty($participant->nphPairedSite) && $participant->nphPairedSite !== 'UNSET') {
            $this->nphPairedSiteSuffix = $this->getSiteSuffix($participant->nphPairedSite);
        }
        if (!empty($participant->biobankId)) {
            $this->biobankId = $participant->biobankId;
        }
        $module1TissueConsent = $this->getModule1TissueConsentStatus();
        $this->module1TissueConsentStatus = $module1TissueConsent['value'];
        $this->module1TissueConsentTime = $module1TissueConsent['time'];
        $this->module = $this->getParticipantModule();
        $this->module1DietStatus = $this->module1DietPeriod = ['LMT' => 'started'];
        $this->module2DietStatus = $this->getModuleDietStatus(2);
        $this->module3DietStatus = $this->getModuleDietStatus(3);
        $this->module2DietPeriod = $this->getModuleDietPeriod(2);
        $this->module3DietPeriod = $this->getModuleDietPeriod(3);
        $this->moduleConsents = $this->getModuleConsents();
    }

    private function getSiteSuffix(string $site): string
    {
        return str_replace(\App\Security\User::SITE_NPH_PREFIX, '', $site);
    }

    private function getParticipantModule(): int
    {
        $nphEnrollmentStatus = $this->rdrData->nphEnrollmentStatus ?? null;
        if ($nphEnrollmentStatus === null) {
            return 1;
        }
        // Get most recent module based on time
        $mostRecentModule = $this->getParticipantMostRecentModule($nphEnrollmentStatus);
        if ($mostRecentModule !== 1) {
            return $mostRecentModule;
        }
        // Fallback check if most recent module is 1
        $moduleMap = [
            '/module2_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 2,
            '/module3_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 3
        ];

        foreach ($moduleMap as $pattern => $moduleNumber) {
            foreach ($nphEnrollmentStatus as $status) {
                $value = $status->value;
                if (preg_match($pattern, $value)) {
                    return $moduleNumber;
                }
            }
        }

        return 1;
    }


    private function getParticipantMostRecentModule(array $nphEnrollmentStatus): int
    {
        $moduleMap = [
            '/module2_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 2,
            '/module3_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 3
        ];

        $mostRecentModule = 1;
        $mostRecentTime = null;

        foreach ($nphEnrollmentStatus as $status) {
            $value = $status->value ?? '';
            $rawTime = $status->time ?? null;
            if ($rawTime === null) {
                continue;
            }
            $time = new DateTime($rawTime);
            foreach ($moduleMap as $pattern => $moduleNumber) {
                if (preg_match($pattern, $value)) {
                    // Check if this status is most recent
                    if ($mostRecentTime === null || $time > $mostRecentTime || ($time == $mostRecentTime && $moduleNumber < $mostRecentModule)) {
                        $mostRecentTime = $time;
                        $mostRecentModule = $moduleNumber;
                    }
                    break;
                }
            }
        }

        return $mostRecentModule;
    }

    private function getModuleDietStatus(int $module): array
    {
        $dietStatus = [];
        $dietStatusField = 'nphModule' . $module . 'DietStatus';
        $nphModuleDietStatus = $this->rdrData->{$dietStatusField} ?? [];
        $this->sortDietStatus($nphModuleDietStatus);
        foreach ($nphModuleDietStatus as $diet) {
            $dietStatuses = array_column($diet->dietStatus, 'status');
            if (in_array(self::DIET_COMPLETED, $dietStatuses)) {
                $dietStatus[$diet->dietName] = self::DIET_COMPLETED;
            } elseif (in_array(self::DIET_DISCONTINUED, $dietStatuses) && !in_array(self::DIET_CONTINUED, $dietStatuses)) {
                $dietStatus[$diet->dietName] = self::DIET_DISCONTINUED;
            } else {
                $key = array_search(self::DIET_CONTINUED, $dietStatuses);
                if ($key !== false) {
                    if ($diet->dietStatus[$key]->current) {
                        $dietStatus[$diet->dietName] = self::DIET_STARTED;
                    } else {
                        $dietStatus[$diet->dietName] = self::DIET_INCOMPLETE;
                    }
                } elseif (in_array(self::DIET_STARTED, $dietStatuses)) {
                    $dietStatus[$diet->dietName] = self::DIET_STARTED;
                }
            }
        }
        return $dietStatus;
    }

    private function getModuleDietPeriod(int $module): array
    {
        if ($module !== $this->getParticipantModule()) {
            return [];
        }
        $dietStatusField = 'nphModule' . $module . 'DietStatus';
        $nphModuleDietStatus = $this->rdrData->{$dietStatusField} ?? [];
        $this->sortDietStatus($nphModuleDietStatus);
        // If diet status is empty diet period 1 will be started
        if (empty($nphModuleDietStatus)) {
            return [
                'PERIOD1' => self::DIET_STARTED
            ];
        }
        $dietPeriods = [];
        $period = 1;
        foreach ($nphModuleDietStatus as $diet) {
            $currentDiet = "PERIOD{$period}";
            $nextDiet = 'PERIOD' . ($period + 1);
            $dietStatuses = array_column($diet->dietStatus, 'status');
            if (in_array(self::DIET_COMPLETED, $dietStatuses)) {
                $dietPeriods[$currentDiet] = self::DIET_COMPLETED;
                if ($period <= 2) {
                    $dietPeriods[$nextDiet] = self::DIET_STARTED;
                }
            } elseif (in_array(self::DIET_DISCONTINUED, $dietStatuses) && !in_array(
                self::DIET_CONTINUED,
                $dietStatuses
            )) {
                $dietPeriods[$currentDiet] = self::DIET_DISCONTINUED;
                $key = array_search(self::DIET_DISCONTINUED, $dietStatuses);
                if ($key !== false) {
                    if ($diet->dietStatus[$key]->current === false && $period <= 2) {
                        $dietPeriods[$nextDiet] = self::DIET_STARTED;
                    }
                }
            } else {
                $key = array_search(self::DIET_CONTINUED, $dietStatuses);
                if ($key !== false) {
                    $dietPeriods[$currentDiet] = self::DIET_INCOMPLETE;
                    if ($diet->dietStatus[$key]->current === false) {
                        if ($period <= 2) {
                            $dietPeriods[$nextDiet] = self::DIET_STARTED;
                        }
                    }
                } elseif (in_array(self::DIET_STARTED, $dietStatuses)) {
                    $dietPeriods[$currentDiet] = self::DIET_STARTED;
                }
            }
            $period++;
        }
        return $dietPeriods;
    }

    private function getModuleConsents()
    {
        $consentStatus = [];
        if (isset($this->rdrData->nphModule1ConsentStatus)) {
            foreach ($this->rdrData->nphModule1ConsentStatus as $consent) {
                switch ($consent->value) {
                    case self::MODULE1_CONSENT_RECONTACT:
                        $consentStatus[1]['Recontact Opt In']['value'] = $consent->optIn;
                        $consentStatus[1]['Recontact Opt In']['time'] = $consent->time;
                        break;
                }
            }
        }
        return $consentStatus;
    }

    private function sortDietStatus(array &$dietStatus): void
    {
        if (!empty($dietStatus)) {
            usort($dietStatus, function ($a, $b) {
                $latestTimeA = max(array_column($a->dietStatus, 'time'));
                $latestTimeB = max(array_column($b->dietStatus, 'time'));
                return strtotime($latestTimeA) - strtotime($latestTimeB);
            });
        }
    }
}
