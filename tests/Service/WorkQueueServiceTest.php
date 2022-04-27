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
        self::assertIsArray($rows);
        self::assertCount(2, $rows);

        $row1 = $rows[0];
        self::assertMatchesRegularExpression('/<a href=".*P100000001.*>LN1/', $row1['lastName']);
        self::assertSame('01/01/1990', $row1['dateOfBirth']);
        self::assertSame('P100000001', $row1['participantId']);
        self::assertStringContainsString('11/3/2021 2:08 pm (Consented Yes)', $row1['primaryConsent']);
        self::assertStringContainsString('11/3/2021 2:08 pm (Consented Yes)', $row1['ehrConsent']);
        self::assertStringContainsString('Active', $row1['ehrConsentExpireStatus']);
        self::assertStringContainsString('(Consent Not Completed)', $row1['dvEhrStatus']);
        self::assertSame('Cohort 3', $row1['consentCohort']);
        self::assertSame('English', $row1['primaryLanguage']);

        $row2 = $rows[1];
        self::assertMatchesRegularExpression('/<a href=".*P200000002.*>LN2/', $row2['lastName']);
        self::assertSame('12/31/1989', $row2['dateOfBirth']);
        self::assertSame('P200000002', $row2['participantId']);
        self::assertStringContainsString('11/2/2021 10:23 am (Consented Yes)', $row2['primaryConsent']);
        self::assertStringContainsString('(Consent Not Completed)', $row2['ehrConsent']);
        self::assertSame('', $row2['ehrConsentExpireStatus']);
        self::assertStringContainsString('(Consent Not Completed)', $row2['dvEhrStatus']);
        self::assertSame('Cohort 3', $row2['consentCohort']);
        self::assertSame('English', $row2['primaryLanguage']);
    }

    public function testGenerateTableRows(): void
    {
        $rows = $this->service->generateTableRows($this->getParticipants());
        self::assertIsArray($rows);
        self::assertCount(2, $rows);

        $row1 = $rows[0];
        self::assertNotEmpty($row1['patientStatusYes']);
        self::assertSame('PTSC Portal', $row1['participantOrigin']);
        self::assertSame('Participant + EHR Consent', $row1['participantStatus']);
        self::assertStringContainsString('Active', $row1['activityStatus']);
        self::assertSame('100 Main St', $row1['address']);
        self::assertSame('Unit 1', $row1['address2']);
        self::assertSame('City1', $row1['city']);
        self::assertSame('AL', $row1['state']);
        self::assertSame('10001', $row1['zip']);
        self::assertStringContainsString('11/3/2021 2:08 pm', $row1['TheBasics']);

        $row2 = $rows[1];
        self::assertEmpty($row2['patientStatusYes']);
        self::assertSame('PTSC Portal', $row2['participantOrigin']);
        self::assertSame('Participant', $row2['participantStatus']);
        self::assertStringContainsString('Active', $row2['activityStatus']);
        self::assertSame('200 Main St', $row2['address']);
        self::assertSame('Unit 2', $row2['address2']);
        self::assertSame('City2', $row2['city']);
        self::assertSame('AZ', $row2['state']);
        self::assertSame('20002', $row2['zip']);
        self::assertStringContainsString('text-danger', $row2['TheBasics']);
    }

    public function testGenerateConsentExportRow()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateConsentExportRow($participants[0], WorkQueue::getWorkQueueConsentColumns());
        self::assertSame([
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
            'English'
        ], $row);
    }

    public function testGenerateExportRow()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0]);
        self::assertSame([
            'LN1',
            'FN1',
            'M1',
            '01/01/1990',
            'P100000001',
            'Y100000001',
            'Participant + EHR Consent',
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
            1,
            '1/12/2022 1:09 pm',
        ], $row);
    }

    public function testDefaultGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueColumns());
        self::assertSame([
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
            1,
            '11/3/2021 2:08 pm',
            0,
            '',
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
            ''
        ], $row);
    }

    public function testContactGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueGroupColumns('contact'));
        self::assertSame([
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
        ], $row);
    }

    public function testDemographicsGroupExportSelected()
    {
        $participants = $this->getParticipants();
        $row = $this->service->generateExportRow($participants[0], WorkQueue::getWorkQueueGroupColumns('demographics'));
        self::assertSame([
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
