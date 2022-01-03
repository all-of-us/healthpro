<?php

namespace App\Tests\Service;

use App\Helper\Participant;
use App\Helper\WorkQueue;
use App\Security\User;
use App\Service\WorkQueueService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class WorkQueueServiceTest extends KernelTestCase
{
    protected $service;

    public function setup(): void
    {
        self::bootKernel();
        $tokenStorage = $this->createTokenStorageWithMockUser();
        self::$container->set('security.token_storage', $tokenStorage);
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
        $this->assertStringContainsString('11/3/2021 2:08 pm (Consented Yes)', $row1['primaryConsent']);
        $this->assertStringContainsString('11/3/2021 2:08 pm (Consented Yes)', $row1['ehrConsent']);
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
        $this->assertSame('100 Main St, City1, AL 10001', $row1['address']);
        $this->assertStringContainsString('11/3/2021 2:08 pm', $row1['TheBasics']);

        $row2 = $rows[1];
        $this->assertEmpty($row2['patientStatusYes']);
        $this->assertSame('PTSC Portal', $row2['participantOrigin']);
        $this->assertSame('Participant', $row2['participantStatus']);
        $this->assertStringContainsString('Active', $row2['activityStatus']);
        $this->assertSame('200 Main St, City2, AZ 20002', $row2['address']);
        $this->assertStringContainsString('text-danger', $row2['TheBasics']);
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
            'Cohort 3',
            'English'
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
            false,
            null,
            'AZ_TUCSON_BANNER_HEALTH',
            '',
            '',
            '',
            '100 Main St',
            null,
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
        ], $row);
    }

    private function createTokenStorageWithMockUser()
    {
        $user = $this->createMock(User::class);
        $user->method('getTimezone')->willReturn('America/Chicago');
        $token = new PreAuthenticatedToken($user, null, 'main', ['ROLE_USER']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        return $tokenStorage;
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
