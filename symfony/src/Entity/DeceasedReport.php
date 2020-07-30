<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class DeceasedReport
{
    const MECHANISMS = [
        'EHR' => 'Electronic Health Record (EHR)',
        'ATTEMPTED_CONTACT' => 'Attempted to contact participant',
        'NEXT_KIN_HPO' => 'Next of kin contacted HPO',
        'NEXT_KIN_SUPPORT' => 'Next of kin contacted Support Center',
        'OTHER' => 'Other'
    ];

    const STATUSES = [
        'preliminary' => 'Pending Approval',
        'canceled' => 'Rejected',
        'final' => 'Approved'
    ];

    const NK_RELATIONSHIPS = [
        'PRN' => 'Parent',
        'CHILD' => 'Child',
        'SIB' => 'Sibling',
        'SPS' => 'Spouse',
        'O' => 'Other'
    ];

    private $id;

    private $participantId;

    private $dateOfDeath;

    private $causeOfDeath;

    private $reportMechanism;

    private $reportMechanismOtherDescription;

    private $reportStatus;

    private $submittedBy;

    private $submittedOn;

    private $nextOfKinName;

    private $nextOfKinRelationship;

    private $nextOfKinTelephoneNumber;

    private $nextOfKinEmail;

    private $reviewedBy;

    private $reviewedOn;

    private $denialReason;

    private $denialReasonOtherDescription;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getParticipantId()
    {
        return $this->participantId;
    }

    public function setParticipantId($participantId)
    {
        $this->participantId = $participantId;
        return $this;
    }

    public function getDateOfDeath(): ?\DateTime
    {
        return $this->dateOfDeath;
    }

    public function setDateOfDeath(?\DateTime $dateOfDeath)
    {
        $this->dateOfDeath = $dateOfDeath;
        return $this;
    }

    public function getCauseOfDeath()
    {
        return $this->causeOfDeath;
    }

    public function setCauseOfDeath($causeOfDeath)
    {
        $this->causeOfDeath = $causeOfDeath;
        return $this;
    }

    public function getReportMechanism()
    {
        return $this->reportMechanism;
    }

    public function setReportMechanism($reportMechanism)
    {
        $this->reportMechanism = $reportMechanism;
        return $this;
    }

    public function getReportMechanismOtherDescription()
    {
        return $this->reportMechanismOtherDescription;
    }

    public function setReportMechanismOtherDescription($reportMechanismOtherDescription)
    {
        $this->reportMechanismOtherDescription = $reportMechanismOtherDescription;
        return $this;
    }

    public function getReportStatus()
    {
        return $this->reportStatus;
    }

    public function setReportStatus($reportStatus)
    {
        $this->reportStatus = $reportStatus;
        return $this;
    }

    public function getSubmittedBy()
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy($submittedBy)
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getSubmittedOn(): \DateTime
    {
        return $this->submittedOn;
    }

    public function setSubmittedOn(\DateTime $submittedOn)
    {
        $this->submittedOn = $submittedOn;
        return $this;
    }

    public function getNextOfKinName()
    {
        return $this->nextOfKinName;
    }

    public function setNextOfKinName($nextOfKinName)
    {
        $this->nextOfKinName = $nextOfKinName;
        return $this;
    }

    public function getNextOfKinRelationship()
    {
        return $this->nextOfKinRelationship;
    }

    public function setNextOfKinRelationship($nextOfKinRelationship)
    {
        $this->nextOfKinRelationship = $nextOfKinRelationship;
        return $this;
    }

    public function getNextOfKinTelephoneNumber()
    {
        return $this->nextOfKinTelephoneNumber;
    }

    public function setNextOfKinTelephoneNumber($nextOfKinTelephoneNumber)
    {
        $this->nextOfKinTelephoneNumber = $nextOfKinTelephoneNumber;
        return $this;
    }

    public function getNextOfKinEmail()
    {
        return $this->nextOfKinEmail;
    }

    public function setNextOfKinEmail($nextOfKinEmail)
    {
        $this->nextOfKinEmail = $nextOfKinEmail;
        return $this;
    }

    public function getReviewedBy()
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy($reviewedBy)
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedOn(): ?\DateTime
    {
        return $this->reviewedOn;
    }

    public function setReviewedOn(\DateTime $reviewedOn)
    {
        $this->reviewedOn = $reviewedOn;
        return $this;
    }

    public function getDenialReason()
    {
        return $this->denialReason;
    }

    public function setDenialReason($denialReason)
    {
        $this->denialReason = $denialReason;
        return $this;
    }

    public function getDenialReasonOtherDescription()
    {
        return $this->denialReasonOtherDescription;
    }

    public function setDenialReasonOtherDescription($denialReasonOtherDescription)
    {
        $this->denialReasonOtherDescription = $denialReasonOtherDescription;
        return $this;
    }

    /* Model Methods */

    public function getReportmechanismDisplay()
    {
        if (isset(self::MECHANISMS[$this->reportMechanism])) {
            return self::MECHANISMS[$this->reportMechanism];
        }
        return $this->reportMechanism;
    }

    public function getReportStatusDisplay()
    {
        if (isset(self::STATUSES[$this->reportStatus])) {
            return self::STATUSES[$this->reportStatus];
        }
        return $this->reportStatus;
    }

    public function getNextOfKinRelationshipDisplay()
    {
        if (isset(self::NK_RELATIONSHIPS[$this->nextOfKinRelationship])) {
            return self::NK_RELATIONSHIPS[$this->nextOfKinRelationship];
        }
        return $this->nextOfKinRelationship;
    }

    public function loadFromFhirObservation($report)
    {
        $this->setId($report->identifier[0]->value);
        $this->setParticipantId($report->subject->reference);
        $this->setDateOfDeath($report->effectiveDateTime ? new \DateTime($report->effectiveDateTime) : null);
        $this->setReportMechanism($report->encounter->reference);
        if (isset($report->encounter->display)) {
            $this->setReportMechanismOtherDescription($report->encounter->display);
        }
        $this->setReportStatus($report->status);
        foreach ($report->performer as $performer) {
            switch ($performer->extension[0]->url) {
                case 'https://www.pmi-ops.org/observation/authored':
                    $this->setSubmittedBy($performer->reference);
                    $this->setSubmittedOn(new \DateTime($performer->extension[0]->valueDateTime));
                    break;
                case 'https://www.pmi-ops.org/observation/reviewed':
                    $this->setReviewedBy($performer->reference);
                    $this->setReviewedOn(new \DateTime($performer->extension[0]->valueDateTime));
                    break;
            }

        }
        $this->setNextOfKinName(isset($report->extension[0]) ? $report->extension[0]->valueHumanName->text : '');
        $this->setNextOfKinRelationship(isset($report->extension[0]) ? $report->extension[0]->valueHumanName->extension[0]->valueCode : '');
        $this->setNextOfKinTelephoneNumber(isset($report->extension[0]) ? $report->extension[0]->valueHumanName->extension[1]->valueString : '');
        $this->setNextOfKinEmail(isset($report->extension[0]) ? $report->extension[0]->valueHumanName->extension[2]->valueString : '');
        return $this;
    }
}
