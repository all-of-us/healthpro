<?php

namespace App\Tests\Service;

use App\Service\IdVerificationService;
use App\Service\LoggerService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use GuzzleHttp\Psr7\Response;

class IdVerificationServiceTest extends ServiceTestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::getContainer()->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
    }

    public function testRdrObject(): void
    {
        $idVerificationData = $this->getIdVerificationFormData();
        $idVerificationData['verificationType'] = $idVerificationData['verification_type'];
        $idVerificationData['visitType'] = $idVerificationData['visit_type'];
        $idVerificationService = static::getContainer()->get(IdVerificationService::class);
        $rdrObject = $idVerificationService->getRdrObject($idVerificationData);
        self::assertEquals('P123456789', $rdrObject->participantId);
        self::assertEquals('test@example.com', $rdrObject->userEmail);
        self::assertEquals('hpo-site-test', $rdrObject->siteGoogleGroup);
        self::assertEquals('2022-04-19T20:52:23', $rdrObject->verifiedTime);
        self::assertEquals('PHOTO_AND_ONE_OF_PII', $rdrObject->verificationType);
        self::assertEquals('PHYSICAL_MEASUREMENTS_ONLY', $rdrObject->visitType);
    }


    /**
     * @dataProvider createMockResponseDataProvider
     */
    public function testCreateIdVerification($data, $response): void
    {
        $service = $this->getIdVerificationService($data, 'post');
        $result = $service->createIdVerification('P123456789', $this->getIdVerificationFormData());
        self::assertEquals($result, $response);
    }

    public function testGetIdVerifications(): void
    {
        $service = $this->getIdVerificationService($this->getIdVerificationsData(), 'get');
        $result = $service->getIdVerifications('P123456789');
        self::assertEquals('P123456789', $result[0]->participantId);
        self::assertEquals('2022-04-19T20:52:23', $result[0]->verifiedTime);
        self::assertEquals('test@example.com', $result[0]->userEmail);
        self::assertEquals('hpo-site-test', $result[0]->siteGoogleGroup);
        self::assertEquals('PHOTO_AND_ONE_OF_PII', $result[0]->verificationType);
        self::assertEquals('PMB_INITIAL_VISIT', $result[0]->visitType);
    }

    private function getIdVerificationService($data, $type): IdVerificationService
    {
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $mockRdrApiService->method($type)->willReturn($this->getGuzzleResponse($data));
        return new IdVerificationService(
            $mockRdrApiService,
            static::getContainer()->get(SiteService::class),
            static::getContainer()->get(UserService::class),
            $this->createMock(LoggerService::class)
        );
    }

    private function getGuzzleResponse($data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $data);
    }

    private function getIdVerificationFormData(): array
    {
        return [
            'participantId' => 'P123456789',
            'userEmail' => 'test@example.com',
            'siteGoogleGroup' => 'hpo-site-test',
            'verifiedTime' => '2022-04-19T20:52:23',
            'verification_type' => 'PHOTO_AND_ONE_OF_PII',
            'visit_type' => 'PHYSICAL_MEASUREMENTS_ONLY'
        ];
    }

    private function getIdVerificationsData(): string
    {
        return '{"entry": [{"participantId": "P123456789", "verifiedTime": "2022-04-19T20:52:23", "userEmail": "test@example.com", "siteGoogleGroup": "hpo-site-test", "siteName": "Test", "verificationType": "PHOTO_AND_ONE_OF_PII", "visitType": "PMB_INITIAL_VISIT"}]}';
    }

    public function createMockResponseDataProvider()
    {
        return [
            ['{"participantId" : "P123456789", "verificationType": "PHOTO_AND_ONE_OF_PII"}', true],
            ['{"participantId" : "P123456789"}', false]
        ];
    }
}
