<?php
use Pmi\Entities\Participant;

class ParticipantTest extends PHPUnit\Framework\TestCase
{
    public function testMayolinkDob()
    {
        $participant = new Participant((object)[
            'dateOfBirth' => '1999-05-20',
        ]);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));

        $participant = new Participant((object)[
            'dateOfBirth' => '1996-02-29',
        ]);
        $this->assertSame('1996-02-29', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));
    }

    public function testParticipantDisableTestAccessStatus()
    {
        $options = [
            'disableTestAccess' => true,
            'genomicsStartTime' => '2020-03-23T12:44:33',
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        $participant = new Participant((object)[
            'options' => $options,
            'hpoId' => 'TEST',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('test-participant', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testParticipantBasicsStatus()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'UNSET',
            'consentForStudyEnrollment' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('basics', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testParticipantConsentStatus()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('consent', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testParticipantWithdrawalStatusNoUse()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'withdrawalStatus' => 'NO_USE'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('withdrawal', $participant->statusReason);
        $this->assertSame(true, $participant->isWithdrawn);
    }

    public function testParticipantWithdrawalStatusEarlyOut()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'withdrawalStatus' => 'EARLY_OUT'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('withdrawal', $participant->statusReason);
        $this->assertSame(true, $participant->isWithdrawn);
    }

    public function testParticipantGrorStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'genomicsStartTime' => '2020-03-23T12:44:33',
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        // Assert genomics status (Criteria 1)
        $participant = new Participant((object)[
            'options' => $options,
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'UNSET',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:44:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('genomics', $participant->statusReason);

        // Assert genomics status for cohort 2 (Criteria 2)
        $participant = new Participant((object)[
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'physicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'UNSET',
            'consentForGenomicsROR' => 'UNSET',
            'questionnaireOnDnaProgram' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('genomics', $participant->statusReason);

        // Assert genomics status for cohort 1 (Criteria 3)
        $participant = new Participant((object)[
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'physicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('genomics', $participant->statusReason);
    }

    public function testParticipantEhrStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'genomicsStartTime' => '2020-03-23T12:44:33',
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        // For HPO
        // Assert not submitted ehr consent (UNSET)
        $participant = new Participant((object)[
            'options' => $options,
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForElectronicHealthRecords' => 'UNSET',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:44:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);

        // Assert not submitted ehr consent (SUBMITTED_NOT_SURE)
        $participant = new Participant((object)[
            'options' => $options,
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForElectronicHealthRecords' => 'SUBMITTED_NOT_SURE',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:44:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);

        // Assert UNSET gRoR and Ehr consent
        $participant = new Participant((object)[
            'options' => $options,
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'UNSET',
            'consentForElectronicHealthRecords' => 'UNSET',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:44:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);
    }

    public function testParticipantConsentCohort()
    {
        // Assert cohort 1
        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_1'
        ]);
        $this->assertSame('Cohort 1', $participant->consentCohortText);

        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_1',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 1', $participant->consentCohortText);

        // Assert cohort 2
        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_2'
        ]);
        $this->assertSame('Cohort 2', $participant->consentCohortText);

        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_2',
            'cohort2PilotFlag' => 'UNSET'
        ]);
        $this->assertSame('Cohort 2', $participant->consentCohortText);

        // Assert cohort 2 pilot
        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_2',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 2 Pilot', $participant->consentCohortText);

        // Assert cohort 3
        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_3'
        ]);
        $this->assertSame('Cohort 3', $participant->consentCohortText);

        $participant = new Participant((object)[
            'consentCohort' => 'COHORT_3',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 3', $participant->consentCohortText);
    }

    public function testParticipantProgramUpdateStatus()
    {
        // Assert program update status
        $participant = new Participant((object)[
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'physicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'UNSET',
            'questionnaireOnDnaProgram' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('program-update', $participant->statusReason);

        // Assert participant status to true
        $participant = new Participant((object)[
            'consentForStudyEnrollment' => 'SUBMITTED',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'physicalMeasurementsStatus' => 'COMPLETED',
            'samplesToIsolateDNA' => 'RECEIVED',
            'questionnaireOnDnaProgram' => 'SUBMITTED',
            'consentForGenomicsROR' => 'SUBMITTED',
        ]);
        $this->assertSame(true, $participant->status);
    }

    public function testParticipantPrimaryConsentUpdateStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'genomicsStartTime' => '',
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => '2020-03-24T12:44:33'
        ];
        // Assert program update status
        $participant = new Participant((object)[
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'physicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'UNSET',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:43:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('primary-consent-update', $participant->statusReason);

        // Assert participant status to true
        $participant = new Participant((object)[
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'physicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:45:33'
        ]);
        //$this->assertSame(true, $participant->status);
        $this->assertSame(true, $participant->status);
    }
}
