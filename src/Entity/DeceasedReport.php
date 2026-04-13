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

    private ?string $id = null;

    private ?string $participantId = null;

    private ?\DateTime $dateOfDeath = null;

    private ?string $causeOfDeath = null;

    private ?string $reportMechanism = null;

    private ?string $reportMechanismOtherDescription = null;

    private ?string $reportStatus = null;

    private ?string $submittedBy = null;

    private ?\DateTime $submittedOn = null;

    private ?string $nextOfKinName = null;

    private ?string $nextOfKinRelationship = null;

    private ?string $nextOfKinTelephoneNumber = null;

    private ?string $nextOfKinEmail = null;

    private ?string $reviewedBy = null;

    private ?\DateTime $reviewedOn = null;

    private ?string $denialReason = null;

    private ?string $denialReasonOtherDescription = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
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

    public function getDateOfDeath(): ?\DateTime
    {
        return $this->dateOfDeath;
    }

    public function setDateOfDeath(?\DateTime $dateOfDeath): self
    {
        $this->dateOfDeath = $dateOfDeath;
        return $this;
    }

    public function getCauseOfDeath(): ?string
    {
        return $this->causeOfDeath;
    }

    public function setCauseOfDeath(?string $causeOfDeath): self
    {
        $this->causeOfDeath = $causeOfDeath;
        return $this;
    }

    public function getReportMechanism(): ?string
    {
        return $this->reportMechanism;
    }

    public function setReportMechanism(?string $reportMechanism): self
    {
        $this->reportMechanism = $reportMechanism;
        return $this;
    }

    public function getReportMechanismOtherDescription(): ?string
    {
        return $this->reportMechanismOtherDescription;
    }

    public function setReportMechanismOtherDescription(?string $reportMechanismOtherDescription): self
    {
        $this->reportMechanismOtherDescription = $reportMechanismOtherDescription;
        return $this;
    }

    public function getReportStatus(): ?string
    {
        return $this->reportStatus;
    }

    public function setReportStatus(?string $reportStatus): self
    {
        $this->reportStatus = $reportStatus;
        return $this;
    }

    public function getSubmittedBy(): ?string
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?string $submittedBy): self
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getSubmittedOn(): ?\DateTime
    {
        return $this->submittedOn;
    }

    public function setSubmittedOn(?\DateTime $submittedOn): self
    {
        $this->submittedOn = $submittedOn;
        return $this;
    }

    public function getNextOfKinName(): ?string
    {
        return $this->nextOfKinName;
    }

    public function setNextOfKinName(?string $nextOfKinName): self
    {
        $this->nextOfKinName = $nextOfKinName;
        return $this;
    }

    public function getNextOfKinRelationship(): ?string
    {
        return $this->nextOfKinRelationship;
    }

    public function setNextOfKinRelationship(?string $nextOfKinRelationship): self
    {
        $this->nextOfKinRelationship = $nextOfKinRelationship;
        return $this;
    }

    public function getNextOfKinTelephoneNumber(): ?string
    {
        return $this->nextOfKinTelephoneNumber;
    }

    public function setNextOfKinTelephoneNumber(?string $nextOfKinTelephoneNumber): self
    {
        $this->nextOfKinTelephoneNumber = $nextOfKinTelephoneNumber;
        return $this;
    }

    public function getNextOfKinEmail(): ?string
    {
        return $this->nextOfKinEmail;
    }

    public function setNextOfKinEmail(?string $nextOfKinEmail): self
    {
        $this->nextOfKinEmail = $nextOfKinEmail;
        return $this;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?string $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedOn(): ?\DateTime
    {
        return $this->reviewedOn;
    }

    public function setReviewedOn(?\DateTime $reviewedOn): self
    {
        $this->reviewedOn = $reviewedOn;
        return $this;
    }

    public function getDenialReason(): ?string
    {
        return $this->denialReason;
    }

    public function setDenialReason(?string $denialReason): self
    {
        $this->denialReason = $denialReason;
        return $this;
    }

    public function getDenialReasonOtherDescription(): ?string
    {
        return $this->denialReasonOtherDescription;
    }

    public function setDenialReasonOtherDescription(?string $denialReasonOtherDescription): self
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

    public function loadFromFhirObservation(\stdClass $report): self
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
