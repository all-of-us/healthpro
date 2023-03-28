<?php

namespace App\Entity;

class DeceasedReport
{
    public const MECHANISMS = [
        'EHR' => 'Electronic Health Record (EHR)',
        'ATTEMPTED_CONTACT' => 'Attempted to contact participant',
        'NEXT_KIN_HPO' => 'Next of kin contacted HPO',
        'NEXT_KIN_SUPPORT' => 'Next of kin contacted Support Center',
        'OTHER' => 'Other'
    ];

    public const STATUSES = [
        'preliminary' => 'Pending Acceptance',
        'cancelled' => 'Denied',
        'final' => 'Accepted'
    ];

    public const NK_RELATIONSHIPS = [
        'PRN' => 'Parent',
        'CHILD' => 'Child',
        'SIB' => 'Sibling',
        'SPS' => 'Spouse',
        'O' => 'Other'
    ];

    public const DENIAL_REASONS = [
        'INCORRECT_PARTICIPANT' => 'Incorrect Participant',
        'MARKED_IN_ERROR' => 'Marked in Error',
        'INSUFFICENT_INFORMATION' => 'Insufficient Information',
        'OTHER' => 'Other'
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

    // Model Methods

    public function getReportmechanismDisplay(): ?string
    {
        if (isset(self::MECHANISMS[$this->reportMechanism])) {
            return self::MECHANISMS[$this->reportMechanism];
        }
        return $this->reportMechanism;
    }

    public function getReportStatusDisplay(): ?string
    {
        if (isset(self::STATUSES[$this->reportStatus])) {
            return self::STATUSES[$this->reportStatus];
        }
        return $this->reportStatus;
    }

    public function getNextOfKinRelationshipDisplay(): ?string
    {
        if (isset(self::NK_RELATIONSHIPS[$this->nextOfKinRelationship])) {
            return self::NK_RELATIONSHIPS[$this->nextOfKinRelationship];
        }
        return $this->nextOfKinRelationship;
    }

    public function getDenialReasonDisplay(): ?string
    {
        if (isset(self::DENIAL_REASONS[$this->denialReason])) {
            return self::DENIAL_REASONS[$this->denialReason];
        }
        return $this->denialReason;
    }

    public function loadFromFhirObservation($report)
    {
        $this->setId($report->identifier[0]->value);
        $this->setParticipantId($report->subject->reference);
        if (property_exists($report, 'effectiveDateTime') && $report->effectiveDateTime) {
            $this->setDateOfDeath(new \DateTime($report->effectiveDateTime));
        }
        if (property_exists($report, 'valueString') && $report->valueString) {
            $this->setCauseOfDeath($report->valueString);
        }
        $this->setReportMechanism($report->encounter->reference);
        if (property_exists($report->encounter, 'display')) {
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

        if (property_exists($report, 'extension')
            && count($report->extension) > 0
        ) {
            foreach ($report->extension as $reportExtension) {
                switch ($reportExtension->url) {
                    case 'https://www.pmi-ops.org/observation-denial-reason':
                        $this->setDenialReason($reportExtension->valueReference->reference);
                        if (property_exists($reportExtension->valueReference, 'display')
                            && $reportExtension->valueReference->display
                        ) {
                            $this->setDenialReasonOtherDescription($reportExtension->valueReference->display);
                        }
                        break;
                }
            }
        }

        if (property_exists($report, 'extension')
            && property_exists($report, 'extension')
            && count($report->extension) > 0
            && property_exists($report->extension[0], 'valueHumanName')
            && $report->extension[0]->valueHumanName
        ) {
            $this->setNextOfKinName($report->extension[0]->valueHumanName->text);
            $this->setNextOfKinRelationship($report->extension[0]->valueHumanName->extension[0]->valueCode);
            foreach ($report->extension[0]->valueHumanName->extension as $ext) {
                switch ($ext->url) {
                    case 'http://hl7.org/fhir/ValueSet/relatedperson-relationshiptype':
                        $this->setNextOfKinRelationship($ext->valueCode);
                        break;
                    case 'https://www.pmi-ops.org/phone-number':
                        $this->setNextOfKinTelephoneNumber($ext->valueString);
                        break;
                    case 'https://www.pmi-ops.org/email-address':
                        $this->setNextOfKinEmail($ext->valueString);
                        break;
                }
            }
        }
        return $this;
    }
}
