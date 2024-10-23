<?php

namespace App\Tests\Helper;

use App\Helper\Participant;
use PHPUnit\Framework\TestCase;

class ParticipantTest extends TestCase
{
    public function testMayolinkDob()
    {
        $participant = new Participant((object) [
            'dateOfBirth' => '1999-05-20',
        ]);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));

        $participant = new Participant((object) [
            'dateOfBirth' => '1996-02-29',
        ]);
        $this->assertSame('1996-02-29', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));
    }

    public function testDeceasedParticipantPendingAccessStatus()
    {
        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'deceasedStatus' => 'PENDING'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('deceased-pending', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testDeceasedParticipantApprovedAccessStatus()
    {
        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'deceasedStatus' => 'APPROVED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('deceased-approved', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testParticipantDisableTestAccessStatus()
    {
        $options = [
            'disableTestAccess' => true,
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        $participant = new Participant((object) [
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
        $participant = new Participant((object) [
            'questionnaireOnTheBasics' => 'UNSET',
            'consentForStudyEnrollment' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('basics', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);
    }

    public function testParticipantConsentStatus()
    {
        $participant = new Participant((object) [
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('consent', $participant->statusReason);
        $this->assertSame(false, $participant->isWithdrawn);

        $participant = new Participant((object) [
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'UNSET',
            'withdrawalStatus' => 'NO_USE'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('consent', $participant->statusReason);
    }

    public function testParticipantWithdrawalStatusNoUse()
    {
        $participant = new Participant((object) [
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
        $participant = new Participant((object) [
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
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        // Assert genomics status (Criteria 1)
        $participant = new Participant((object) [
            'options' => $options,
            'consentCohort' => 'COHORT_3',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'UNSET'
        ]);
        $this->assertSame(true, $participant->status);

        // Assert genomics status for cohort 2 (Criteria 2)
        $participant = new Participant((object) [
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'clinicPhysicalMeasurementsStatus' => 'COMPLETED',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'UNSET',
            'questionnaireOnDnaProgram' => 'SUBMITTED'
        ]);
        $this->assertSame(true, $participant->status);

        // Assert genomics status for cohort 1 (Criteria 3)
        $participant = new Participant((object) [
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'clinicPhysicalMeasurementsStatus' => 'COMPLETED',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'UNSET'
        ]);
        $this->assertSame(true, $participant->status);
    }

    public function testParticipantEhrStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => ''
        ];

        // For HPO
        // Assert not submitted ehr consent (UNSET)
        $participant = new Participant((object) [
            'options' => $options,
            'consentCohort' => 'COHORT_3',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForElectronicHealthRecords' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);

        // Assert not submitted ehr consent (SUBMITTED_NOT_SURE)
        $participant = new Participant((object) [
            'options' => $options,
            'consentCohort' => 'COHORT_3',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForElectronicHealthRecords' => 'SUBMITTED_NOT_SURE'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);

        // Assert UNSET gRoR and Ehr consent
        $participant = new Participant((object) [
            'options' => $options,
            'consentCohort' => 'COHORT_3',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'UNSET',
            'consentForElectronicHealthRecords' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('ehr-consent', $participant->statusReason);
    }

    public function testParticipantConsentCohort()
    {
        // Assert cohort 1
        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_1'
        ]);
        $this->assertSame('Cohort 1', $participant->consentCohortText);

        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_1',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 1', $participant->consentCohortText);

        // Assert cohort 2
        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_2'
        ]);
        $this->assertSame('Cohort 2', $participant->consentCohortText);

        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_2',
            'cohort2PilotFlag' => 'UNSET'
        ]);
        $this->assertSame('Cohort 2', $participant->consentCohortText);

        // Assert cohort 2 pilot
        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_2',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 2 Pilot', $participant->consentCohortText);

        // Assert cohort 3
        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_3'
        ]);
        $this->assertSame('Cohort 3', $participant->consentCohortText);

        $participant = new Participant((object) [
            'consentCohort' => 'COHORT_3',
            'cohort2PilotFlag' => 'COHORT_2_PILOT'
        ]);
        $this->assertSame('Cohort 3', $participant->consentCohortText);
    }

    public function testParticipantProgramUpdateStatus()
    {
        // Assert program update status
        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'clinicPhysicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'UNSET',
            'questionnaireOnDnaProgram' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('program-update', $participant->statusReason);

        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'clinicPhysicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'RECEIVED',
            'questionnaireOnDnaProgram' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('program-update', $participant->statusReason);

        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'clinicPhysicalMeasurementsStatus' => 'COMPLETED',
            'samplesToIsolateDNA' => 'UNSET',
            'questionnaireOnDnaProgram' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('program-update', $participant->statusReason);

        // Assert participant status to true
        $participant = new Participant((object) [
            'consentForStudyEnrollment' => 'SUBMITTED',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentCohort' => 'COHORT_2',
            'clinicPhysicalMeasurementsStatus' => 'COMPLETED',
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
            'siteType' => 'hpo',
            'cohortOneLaunchTime' => '2020-03-24T12:44:33'
        ];
        // Assert program update status
        $participant = new Participant((object) [
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'clinicPhysicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'UNSET',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:43:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('primary-consent-update', $participant->statusReason);

        $participant = new Participant((object) [
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'clinicPhysicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:43:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('primary-consent-update', $participant->statusReason);

        $participant = new Participant((object) [
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'clinicPhysicalMeasurementsStatus' => 'COMPLETED',
            'samplesToIsolateDNA' => 'UNSET',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:43:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('primary-consent-update', $participant->statusReason);

        // Assert participant status to true
        $participant = new Participant((object) [
            'options' => $options,
            'consentForStudyEnrollment' => 'SUBMITTED',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentCohort' => 'COHORT_1',
            'clinicPhysicalMeasurementsStatus' => 'UNSET',
            'samplesToIsolateDNA' => 'RECEIVED',
            'consentForGenomicsROR' => 'SUBMITTED',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:45:33'
        ]);
        $this->assertSame(true, $participant->status);
    }

    public function testActivityStatus()
    {
        // Withdrawn
        $participant = new Participant((object) [
            'withdrawalStatus' => 'NO_USE'
        ]);
        $this->assertSame('withdrawn', $participant->activityStatus);
        $participant = new Participant((object) [
            'withdrawalStatus' => 'EARLY_OUT'
        ]);
        $this->assertSame('withdrawn', $participant->activityStatus);

        // Deactivated
        $participant = new Participant((object) [
            'withdrawalStatus' => 'NOT_WITHDRAWN',
            'suspensionStatus' => 'NO_CONTACT'
        ]);
        $this->assertSame('deactivated', $participant->activityStatus);

        // Deceased
        $participant = new Participant((object) [
            'withdrawalStatus' => 'NOT_WITHDRAWN',
            'deceasedStatus' => 'PENDING'
        ]);
        $this->assertSame('deceased', $participant->activityStatus);
        $participant = new Participant((object) [
            'withdrawalStatus' => 'NOT_WITHDRAWN',
            'deceasedStatus' => 'APPROVED'
        ]);
        $this->assertSame('deceased', $participant->activityStatus);

        // Priority
        $participant = new Participant((object) [
            'withdrawalStatus' => 'NO_USE',
            'deceasedStatus' => 'APPROVED'
        ]);
        $this->assertSame('withdrawn', $participant->activityStatus);

        $participant = new Participant((object) [
            'withdrawalStatus' => 'NOT_WITHDRAWN',
            'suspensionStatus' => 'NO_CONTACT',
            'deceasedStatus' => 'APPROVED'
        ]);
        $this->assertSame('deactivated', $participant->activityStatus);
    }

    public function getPediatricWeightBreakpointProvider()
    {
        return
            [
                [2.5, 2.3],
                [2.5, 2.4],
                [5.0, 2.5],
                [5.0, 4.8],
                [5.0, 4.9],
                [16.4, 5],
                [16.4, 16.2],
                [16.4, 16.3],
                [9999.0, 16.4],
                [9999.0, 16.5],
                [9999.0, 9998.9]
            ];
    }

    /**
     * @dataProvider getPediatricWeightBreakpointProvider
     */
    public function testGetPediatricWeightBreakpoint($expectedResult, $weight)
    {
        $participant = new Participant((object) [
            'dateOfBirth' => '2010-01-01',
        ]);
        $this->assertSame($expectedResult, $participant->getPediatricWeightBreakpoint($weight));
    }
}
