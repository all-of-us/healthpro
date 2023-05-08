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
    public $id;
    public $cacheTime;
    public $rdrData;
    public $dob;
    public $nphPairedSiteSuffix;
    public $module;
    public $module1TissueConsentStatus;

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

    private function getModule1TissueConsentStatus(): ?bool
    {
        foreach ($this->rdrData->nphModule1ConsentStatus as $consent) {
            if ($consent->value === self::MODULE1_CONSENT_TISSUE && $consent->optin === self::OPTIN_PERMIT) {
                return true;
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
    }

    private function getSiteSuffix(string $site): string
    {
        return str_replace(\App\Security\User::SITE_NPH_PREFIX, '', $site);
    }

    private function getParticipantModule(): int
    {
        //TODO:: retrieve this from RDR participant api
        return 1;
    }
}
