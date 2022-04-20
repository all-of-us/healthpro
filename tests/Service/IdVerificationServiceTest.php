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
        $this->service = $this->getIdVerificationService();
    }

    public function testRdrObject(): void
    {
        $idVerificationData = $this->getIdVerificationData();
        $rdrObject = $this->service->getRdrObject('P123456789', $idVerificationData);
        self::assertEquals('test@example.com', $rdrObject->userEmail);
        self::assertEquals('hpo-site-test', $rdrObject->siteGoogleGroup);
        self::assertEquals('PHOTO_AND_ONE_OF_PII', $rdrObject->verificationType);
        self::assertEquals('PHYSICAL_MEASUREMENTS_ONLY', $rdrObject->visitType);
    }

    public function testCreateIdVerification(): void
    {
        $result = $this->service->createIdVerification('P123456789', $this->getIdVerificationData());
        self::assertTrue($result);
    }

    public function testGetIdVerifications(): void
    {
        $result = $this->service->getIdVerifications('P123456789');
        self::assertEquals('P123456789', $result[0]->participantId);
        self::assertEquals('2022-04-19T20:52:23', $result[0]->verifiedTime);
        self::assertEquals('test@example.com', $result[0]->userEmail);
        self::assertEquals('hpo-site-test', $result[0]->siteGoogleGroup);
        self::assertEquals('PHOTO_AND_ONE_OF_PII', $result[0]->verificationType);
        self::assertEquals('PMB_INITIAL_VISIT', $result[0]->visitType);
    }

    private function getIdVerificationService(): IdVerificationService
    {
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $createResponseData = $this->getCreateMockResponseData();
        $idVerificationsData = $this->getIdVerificationsData();
        $mockRdrApiService->method('post')->willReturn($this->getGuzzleResponse($createResponseData));
        $mockRdrApiService->method('get')->willReturn($this->getGuzzleResponse($idVerificationsData));
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

    private function getIdVerificationData(): array
    {
        $idVerificationData = [
            'verification_type' => 'PHOTO_AND_ONE_OF_PII',
            'visit_type' => 'PHYSICAL_MEASUREMENTS_ONLY'
        ];
        return $idVerificationData;
    }

    private function getCreateMockResponseData(): string
    {
        return '{"participantId" : "P123456789", "verificationType": "PHOTO_AND_ONE_OF_PII"}';
    }

    private function getIdVerificationsData(): string
    {
        return '{"entry": [{"participantId": "P123456789", "verifiedTime": "2022-04-19T20:52:23", "userEmail": "test@example.com", "siteGoogleGroup": "hpo-site-test", "siteName": "Test", "verificationType": "PHOTO_AND_ONE_OF_PII", "visitType": "PMB_INITIAL_VISIT"}]}';
    }
}
