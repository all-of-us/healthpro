<?php

namespace App\Tests\Service;

use App\Service\IdVerificationService;
use App\Service\LoggerService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;

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

    private function getIdVerificationService(): IdVerificationService
    {
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $stream = Utils::streamFor($this->getCreateMockResponseData());
        $response = new Psr7\Response(200, ['Content-Type' => 'application/json'], $stream);
        $mockRdrApiService->method('post')->willReturn($response);
        return new IdVerificationService(
            $mockRdrApiService,
            static::getContainer()->get(SiteService::class),
            static::getContainer()->get(UserService::class),
            $this->createMock(LoggerService::class)
        );
    }
}
