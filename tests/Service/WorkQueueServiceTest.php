<?php

namespace App\Tests\Service;

use App\Helper\Participant;
use App\Helper\WorkQueue;
use App\Service\WorkQueueService;

class WorkQueueServiceTest extends ServiceTestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $this->service = static::$container->get(WorkQueueService::class);
    }

    public function testGenerateConsentTableRows(): void
    {
        $rows = $this->service->generateConsentTableRows($this->getParticipants());
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);

        $row1 = $rows[0];
        $this->assertMatchesRegularExpression('/<a href=".*P100000001.*>LN1/', $row1['lastName']);
        $this->assertSame('01/01/1990', $row1['dateOfBirth']);
        $this->assertSame('P100000001', $row1['participantId']);
        $this->assertStringContainsString('8/3/2022 3:00 pm (Consented Yes)', $row1['primaryConsent']);
        $this->assertStringContainsString('8/3/2022 3:00 pm (Consented Yes)', $row1['ehrConsent']);
        $this->assertStringContainsString('Active', $row1['ehrConsentExpireStatus']);
        $this->assertStringContainsString('(Consent Not Completed)', $row1['dvEhrStatus']);
        $this->assertSame('Cohort 3', $row1['consentCohort']);
        $this->assertSame('English', $row1['primaryLanguage']);

        $row2 = $rows[1];
        $this->assertMatchesRegularExpression('/<a href=".*P200000002.*>LN2/', $row2['lastName']);
        $this->assertSame('12/31/1989', $row2['dateOfBirth']);
        $this->assertSame('P200000002', $row2['participantId']);
        $this->assertStringContainsString('11/2/2021 10:23 am (Consented Yes)', $row2['primaryConsent']);
        $this->assertStringContainsString('(Consent Not Completed)', $row2['ehrConsent']);
        $this->assertSame('', $row2['ehrConsentExpireStatus']);
        $this->assertStringContainsString('(Consent Not Completed)', $row2['dvEhrStatus']);
        $this->assertSame('Cohort 3', $row2['consentCohort']);
        $this->assertSame('English', $row2['primaryLanguage']);
    }

    public function testGenerateTableRows(): void
    {
        $rows = $this->service->generateTableRows($this->getParticipants());
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);

        $row1 = $rows[0];
        $this->assertNotEmpty($row1['patientStatusYes']);
        $this->assertSame('PTSC Portal', $row1['participantOrigin']);
        $this->assertSame('Participant + EHR Consent', $row1['participantStatus']);
        $this->assertStringContainsString('Active', $row1['activityStatus']);
        $this->assertSame('100 Main St', $row1['address']);
        $this->assertSame('Unit 1', $row1['address2']);
        $this->assertSame('City1', $row1['city']);
        $this->assertSame('AL', $row1['state']);
        $this->assertSame('10001', $row1['zip']);
        $this->assertStringContainsString('11/3/2021 2:08 pm', $row1['TheBasics']);
        $this->assertStringContainsString('7/22/2022', $row1['participantIncentive']);
        $this->assertStringContainsString('7/26/2022', $row1['onsiteIdVerificationTime']);
        $this->assertStringContainsString('8/1/2022', $row1['selfReportedPhysicalMeasurementsStatus']);

        $row2 = $rows[1];
        $this->assertEmpty($row2['patientStatusYes']);
        $this->assertSame('PTSC Portal', $row2['participantOrigin']);
        $this->assertSame('Participant', $row2['participantStatus']);
        $this->assertStringContainsString('Active', $row2['activityStatus']);
        $this->assertSame('200 Main St', $row2['address']);
        $this->assertSame('Unit 2', $row2['address2']);
        $this->assertSame('City2', $row2['city']);
        $this->assertSame('AZ', $row2['state']);
        $this->assertSame('20002', $row2['zip']);
        $this->assertStringContainsString('text-danger', $row2['TheBasics']);
        $this->assertStringContainsString('7/23/2022', $row2['participantIncentive']);
        $this->assertStringContainsString('7/27/2022', $row2['onsiteIdVerificationTime']);
        $this->assertStringContainsString('text-danger', $row2['selfReportedPhysicalMeasurementsStatus']);
    }

    public function testGenerateConsentExportRow()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateConsentExportRow($participants[0], WorkQueue::getWorkQueueConsentColumns());
        $this->assertSame([
            'LN1',
            'FN1',
            'M1',
            '01/01/1990',
            'P100000001',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            'Cohort 3',
            'English',
            '11/3/2021 2:08 pm',
            '11/3/2021 2:08 pm',
            '8/3/2022 3:00 pm',
            '8/3/2022 3:00 pm',
            0,
            ''
        ], $row);
    }

    public function testGenerateExportRow()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0]);
        $this->assertSame([
            'LN1',
            'FN1',
            'M1',
            '01/01/1990',
            'P100000001',
            'Y100000001',
            'Participant + EHR Consent',
            'Does Not Have Core Data',
            '',
            '0',
            '',
            '',
            '0',
            '',
            0,
            '',
            '',
            'PTSC Portal',
            'Cohort 3',
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            1,
            '11/3/2021 2:08 pm',
            'English',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '0',
            '',
            'AZ_TUCSON_BANNER_HEALTH',
            '',
            '',
            '',
            '100 Main St',
            'Unit 1',
            'City1',
            'AL',
            '10001',
            'p1@example.com',
            null,
            '8885551001',
            '1',
            4,
            1,
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:09 pm',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            'bannerdesert',
            'AZ_TUCSON_BANNER_HEALTH',
            1,
            '11/3/2021',
            'bannerdesert',
            '0',
            0,
            null,
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '',
            'Female',
            'Woman',
            'Black or African American',
            'College 4 years or more (College graduate)',
            0,
            '',
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            1,
            '1/12/2022 1:09 pm',
            'bannerdesert',
            '7/26/2022 3:00 pm',
            '7/22/2022',
            1,
            '8/1/2022',
            '8/3/2022 3:00 pm',
            '8/3/2022 3:00 pm',
            1,
            '9/22/2022 3:00 pm',
            0,
            '',
            0,
            '',
            0,
            '',
            0,
            '',
            '0',
            '',
            '0',
            '',
            0,
            '',
            '',
            '',
            '',
            '',
            'Adult Participant',
            'N/A',
            '0',
            '',
            '0',
            '',
            '0',
            '',
            '',
        ], $row);
    }

    public function testDefaultGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueColumns());
        $this->assertSame([
            'LN1',
            'FN1',
            'M1',
            '01/01/1990',
            'P100000001',
            'Participant + EHR Consent',
            '',
            '0',
            '',
            '0',
            '',
            0,
            '',
            '',
            'Cohort 3',
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            1,
            '11/3/2021 2:08 pm',
            'English',
            '1',
            4,
            'bannerdesert',
            'AZ_TUCSON_BANNER_HEALTH',
            1,
            '11/3/2021',
            '0',
            0,
            '',
            '8/3/2022 3:00 pm',
            '8/3/2022 3:00 pm',
            '',
            '',
            '',
            '',
            'Adult Participant'
        ], $row);
    }

    public function testContactGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueGroupColumns('contact'));
        $this->assertSame([
            'LN1',
            'FN1',
            'M1',
            'P100000001',
            0,
            '',
            0,
            '100 Main St',
            'Unit 1',
            'City1',
            'AL',
            '10001',
            'p1@example.com',
            null,
            '8885551001',
            'N/A'
        ], $row);
    }

    public function testDemographicsGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueGroupColumns('demographics'));
        $this->assertSame([
            'LN1',
            'FN1',
            'M1',
            '01/01/1990',
            'Participant + EHR Consent',
            '',
            'Female',
            'Woman',
            'Black or African American',
            'College 4 years or more (College graduate)',
            '',
            '',
            '',
            '',
            ''
        ], $row);
    }

    private function getParticipants()
    {
        $rdrParticipantFixtures = json_decode(file_get_contents(__DIR__ . '/data/participant_summary_search.json'));
        $participants = [];
        foreach ($rdrParticipantFixtures as $rdrParticipant) {
            $participants[] = new Participant($rdrParticipant->resource);
        }

        return $participants;
    }
}
