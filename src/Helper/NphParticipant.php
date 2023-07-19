<?php

namespace App\Helper;

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
    public const OPTIN_PERMIT = 'PERMIT';
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
    public array $module1DietStatus;
    public array $module2DietStatus;
    public array $module3DietStatus;

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

    private function getModule1TissueConsentStatus(): bool
    {
        if (isset($this->rdrData->nphModule1ConsentStatus) && is_array($this->rdrData->nphModule1ConsentStatus)) {
            foreach ($this->rdrData->nphModule1ConsentStatus as $consent) {
                if ($consent->value === self::MODULE1_CONSENT_TISSUE && $consent->optIn === self::OPTIN_PERMIT) {
                    return true;
                }
            }
        }
        return false;
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
        $this->module1TissueConsentStatus = $this->getModule1TissueConsentStatus();
        $this->module = $this->getParticipantModule();
        $this->module1DietStatus = ['LMT' => 'started'];
        $this->module2DietStatus = $this->getModuleDietStatus(2);
        $this->module3DietStatus = $this->getModuleDietStatus(3);
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
        $moduleMap = [
            '/module3_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 3,
            '/module2_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 2,
            '/module1_(complete|dietAssigned|eligibilityConfirmed|consented)/' => 1,
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

    private function getModuleDietStatus(int $module): array
    {
        $dietStatus = [];
        $dietStatusField = 'nphModule' . $module . 'DietStatus';
        $nphModuleDietStatus = $this->rdrData->{$dietStatusField} ?? [];
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
}
