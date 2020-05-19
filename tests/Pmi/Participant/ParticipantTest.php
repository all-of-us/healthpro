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
            'siteType' => 'hpo'
        ];

        $participant = new Participant((object)[
            'options' => $options,
            'hpoId' => 'TEST',
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('test-participant', $participant->statusReason);
    }

    public function testParticipantBasicsStatus()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'UNSET',
            'consentForStudyEnrollment' => 'SUBMITTED'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('basics', $participant->statusReason);
    }

    public function testParticipantConsentStatus()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'UNSET'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('consent', $participant->statusReason);
    }

    public function testParticipantWithdrawalStatus()
    {
        $participant = new Participant((object)[
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'withdrawalStatus' => 'NO_USE'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('withdrawal', $participant->statusReason);
    }

    public function testParticipantGrorStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'genomicsStartTime' => '2020-03-23T12:44:33',
            'siteType' => 'hpo'
        ];

        $participant = new Participant((object)[
            'options' => $options,
            'questionnaireOnTheBasics' => 'SUBMITTED',
            'consentForStudyEnrollment' => 'SUBMITTED',
            'consentForGenomicsROR' => 'UNSET',
            'consentForStudyEnrollmentAuthored' => '2020-03-24T12:44:33'
        ]);
        $this->assertSame(false, $participant->status);
        $this->assertSame('genomics', $participant->statusReason);
    }

    public function testParticipantEhrStatus()
    {
        $options = [
            'disableTestAccess' => false,
            'genomicsStartTime' => '2020-03-23T12:44:33',
            'siteType' => 'hpo'
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
    }
}
