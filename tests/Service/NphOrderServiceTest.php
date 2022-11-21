<?php

namespace App\Tests\Service;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Service\Nph\NphOrderService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;

class NphOrderServiceTest extends ServiceTestCase
{
    protected $service;
    protected $em;
    protected $module1Data;

    public function setUp(): void
    {
        parent::setUp();
        $this->program = 'nph';
        $this->login('test-nph-user1@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(NphOrderService::class);
        $this->em = static::$container->get(EntityManagerInterface::class);
        // Module 1
        $this->module1Data = json_decode(file_get_contents(__DIR__ . '/data/order_module_1.json'), true);
    }

    public function testLoadModules(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000001');
        $this->assertSame($this->module1Data['timePointSamples'], $this->service->getTimePointSamples());
        $this->assertSame($this->module1Data['timePoints'], $this->service->getTimePoints());
        $this->assertSame($this->module1Data['samples'], $this->service->getSamples());

        $this->assertSame($this->module1Data['stoolSamples'], $this->service->getSamplesByType('stool'));
        $this->assertSame($this->module1Data['bloodSamples'], $this->service->getSamplesByType('blood'));
        $this->assertSame($this->module1Data['nailSamples'], $this->service->getSamplesByType('nail'));
    }

    /**
     * @dataProvider sampleTypeDataProvider
     */
    public function testGetSampleType($sampleType, $sampleCode): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000002');
        $this->assertSame($sampleType, $this->service->getSampleType($sampleCode));
    }

    public function sampleTypeDataProvider(): array
    {
        return [
            ['nail', 'NAILB'],
            ['blood', 'SST8P5'],
            ['stool', 'ST2'],
            ['urine', 'URINES'],
            ['saliva', 'SALIVA']
        ];
    }

    public function testCreateOrder()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000003');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $this->assertSame('LMT', $nphOrder->getVisitType());
        $this->assertSame('preLMT', $nphOrder->getTimepoint());
        $this->assertSame('saliva', $nphOrder->getOrderType());
    }

    public function testCreateSample()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000004');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $this->service->createSample('SALIVA', $nphOrder);

        $nphSample = $this->em->getRepository(NphSample::class)->findOneBy(['nphOrder' => $nphOrder]);
        $this->assertSame('SALIVA', $nphSample->getSampleCode());
    }

    public function testCreateOrdersAndSamples()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000005');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);
        $this->assertSame($this->module1Data['formData'], $this->service->getExistingOrdersData());
    }

    public function testGetSamplesWithOrderIds()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000006');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);

        $nphOrders = $this->em->getRepository(NphOrder::class)->findBy([
            'participantId' => 'P0000000006', 'visitType' => 'LMT'
        ]);
        $samplesWithOrderIds = $this->service->getSamplesWithOrderIds();
        $timePointSamples = [
            ['preLMT', 'saliva', 'SALIVA'],
            ['preLMT', 'urine', 'URINES'],
            ['preLMT', 'nail', 'NAILB'],
            ['30min', 'blood', 'SST8P5'],
        ];
        foreach ($nphOrders as $nphOrder) {
            foreach ($timePointSamples as $timePointSample) {
                $timePoint = $timePointSample[0];
                $sampleType = $timePointSample[1];
                $sampleCode = $timePointSample[2];
                if ($nphOrder->getTimepoint() === $timePoint) {
                    if ($nphOrder->getOrderType() === $sampleType) {
                        $this->assertSame($nphOrder->getOrderId(), $samplesWithOrderIds[$timePoint][$sampleCode]);
                    }
                }
            }
        }
    }
}
