<?php

namespace App\Tests\Service;

use App\Service\IdVerificationService;
use App\Service\SiteService;

class IdVerificationServiceTest extends ServiceTestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(IdVerificationService::class);
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

    private function getIdVerificationData(): array
    {
        $idVerificationData = [
            'verification_type' => 'PHOTO_AND_ONE_OF_PII',
            'visit_type' => 'PHYSICAL_MEASUREMENTS_ONLY'
        ];
        return $idVerificationData;
    }
}
