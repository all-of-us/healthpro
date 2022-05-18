<?php

namespace App\Tests\Service;

use App\Service\IncentiveImportService;
use App\Service\SiteService;

class IncentiveImportServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(IncentiveImportService::class);
    }

    /**
     * @dataProvider emailDataProvider
     */

    public function testValidEmail($email, $isValid): void
    {
        $result = $this->service->isValidEmail($email);
        $this->assertEquals($result, $isValid);
    }

    public function emailDataProvider()
    {
        return [
            ['test-1@pmi-ops.org', true],
            ['test-2@pmiops.org', false],
            ['test-3@ops-pmi.org', false],
            ['test-4@pmi-ops.org@ops-pmi.org', false],
            ['pmi-ops.org@ops-pmi.org', false]
        ];
    }
}
