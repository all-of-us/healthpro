<?php

namespace App\Tests\Service;

use App\Service\Nph\NphOrderService;
use App\Service\SiteService;

class NphOrderServiceTest extends ServiceTestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(NphOrderService::class);
    }

    public function testLoadModules(): void
    {
        // Module 1
        $module1Data = json_decode(file_get_contents(__DIR__ . '/data/order_module_1.json'), true);
        $this->service->loadModules(1, 'LMT', 'P0000000001');
        $this->assertSame($module1Data['timePointSamples'], $this->service->getTimePointSamples());
        $this->assertSame($module1Data['timePoints'], $this->service->getTimePoints());
        $this->assertSame($module1Data['samples'], $this->service->getSamples());

        $this->assertSame($module1Data['stoolSamples'], $this->service->getSamplesByType('stool'));
        $this->assertSame($module1Data['bloodSamples'], $this->service->getSamplesByType('blood'));
        $this->assertSame($module1Data['nailSamples'], $this->service->getSamplesByType('nail'));
    }
}
