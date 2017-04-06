<?php
namespace Pmi\Entities;

use Pmi\Util;
use Pmi\Drc\CodeBook;

class Participant
{
    public $status = false;
    public $id;
    protected $rdrData;

    public function __construct($rdrParticipant = null)
    {
        if (is_object($rdrParticipant)) {
            $this->rdrData = $rdrParticipant;
            $this->parseRdrParticipant($rdrParticipant);
        }
    }

    public function parseRdrParticipant($participant)
    {
        if (!is_object($participant)) {
            return;
        }

        // Use participant id as id
        if (isset($participant->participantId)) {
            $this->id = $participant->participantId;
        }

        // HealthPro status is active if participant is consented AND has completed basics survey
        if (!empty($participant->questionnaireOnTheBasics) && $participant->questionnaireOnTheBasics === 'SUBMITTED') {
            $this->status = true;
        }
        if (empty($participant->consentForStudyEnrollment) || $participant->consentForStudyEnrollment !== 'SUBMITTED') {
            $this->status = false;
        }

        // Map gender identity to gender options for MayoLINK.  TODO: should we switch to using participant sex if populated?
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
    }

    public function getShortId()
    {
        if (strlen($this->id) >= 36) {
            return strtoupper(Util::shortenUuid($this->id));
        } else {
            return $this->id;
        }
    }

    public function getMayolinkDob($type = null)
    {
        if ($type == 'kit') {
            return new \DateTime('1960-01-01');
        } else {
            $mlDob = new \DateTime();
            $mlDob->setDate(1960, $this->dob->format('m'), $this->dob->format('d'));
            return $mlDob;
        }
    }

    public function getAddress()
    {
        $address = '';
        if ($this->streetAddress) {
            $address .= $this->streetAddress;
            if ($this->city || $this->state || $this->zipCode) {
                $address .= ', ';
            }
        }
        if ($this->city) {
            $address .= $this->city;
            if ($this->state) {
                $address .= ', ';
            } else {
                $address .= ' ';
            }
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
}
