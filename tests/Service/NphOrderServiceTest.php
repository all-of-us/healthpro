<?php

namespace App\Tests\Service;

use App\Entity\NphOrder;
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

    /**
     * @dataProvider sampleLabelsDataProvider
     */
    public function testGetSamplesWithLabels($timePoint, $orderType, $sampleCode, $sampleLabel): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000003');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $this->service->createSample($sampleCode, $nphOrder);
        $expectedSampleLabels = [
            $sampleCode => $sampleLabel
        ];
        $this->assertSame($expectedSampleLabels, $this->service->getSamplesWithLabels($nphOrder->getNphSamples()));
    }

    public function sampleLabelsDataProvider(): array
    {
        return [
            ['preLMT', 'urine', 'URINES', 'Spot Urine'],
            ['preLMT', 'saliva', 'SALIVA', 'Saliva'],
            ['preLMT', 'nail', 'NAILB', 'Big Toenails'],
            ['preLMT', 'stool', 'ST1', '95% Ethanol Tube 1'],
            ['30min', 'blood', 'SST8P5', '8.5 mL SST'],
            ['30min', 'blood', 'PST8', '8 mL PST'],
        ];
    }

    public function testCreateOrder()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000004');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $this->assertSame('LMT', $nphOrder->getVisitType());
        $this->assertSame('preLMT', $nphOrder->getTimepoint());
        $this->assertSame('saliva', $nphOrder->getOrderType());
    }

    public function testCreateSample()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000005');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $nphSample = $this->service->createSample('SALIVA', $nphOrder);

        $this->assertSame('SALIVA', $nphSample->getSampleCode());
    }

    public function testCreateOrdersAndSamples()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000006');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);
        $this->assertSame($this->module1Data['formData'], $this->service->getExistingOrdersData());
    }

    public function testGetSamplesWithOrderIds()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000007');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);

        $nphOrders = $this->em->getRepository(NphOrder::class)->findBy([
            'participantId' => 'P0000000007', 'visitType' => 'LMT'
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

    /**
     * @dataProvider orderCollectionDataProvider
     */
    public function testSaveOrderCollection(
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $notes,
        $metaData = []): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008');
        if ($orderType === 'stool') {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            $nphSample = $this->service->createSample($sampleCode, $nphOrder, 'T0000000001');
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            $nphSample = $this->service->createSample($sampleCode, $nphOrder);
        }
        $collectionFormData = [
            $sampleCode => true,
            "{$sampleCode}CollectedTs" => $collectedTs,
            "{$sampleCode}Notes" => $notes,
        ];
        if ($orderType === 'urine' || $orderType === 'stool') {
            foreach ($metaData as $type => $data) {
                $collectionFormData[$type] = $data;
            }
         }
        $this->service->saveOrderCollection($collectionFormData, $nphOrder);
        $this->assertSame($collectedTs, $nphSample->getCollectedTs());
        $this->assertSame($notes, $nphSample->getCollectedNotes());
        $this->assertSame($collectionFormData, $this->service->getExistingOrderCollectionData($nphOrder));

        if ($metaData) {
            $this->assertSame(json_encode($metaData), $this->service->jsonEncodeMetadata($collectionFormData,
                array_keys($metaData)));
        }
    }

    public function orderCollectionDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-11-18');
        $urineMetaData = [
            'urineColor' => 1,
            'urineClarity' => 'clean'
        ];
        $stoolMetaData = [
            'bowelType' => 'difficult',
            'bowelQuality' => 'normal'
        ];
        return [
            ['preLMT', 'urine', 'URINES', $collectedTs, 'Test Notes 1', $urineMetaData],
            ['preLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 2'],
            ['preLMT', 'stool', 'ST1', $collectedTs, 'Test Notes 3', $stoolMetaData],
            ['30min', 'blood', 'SST8P5', $collectedTs, 'Test Notes 4'],
            ['postLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 5'],
        ];
    }

    public function testGenerateOrderAndSampleIds(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008');

        $orderId = $this->service->generateOrderId();
        $this->assertSame(10, strlen($orderId));
        $this->assertNotEquals(0, $orderId[0]);

        $sampleId = $this->service->generateSampleId();
        $this->assertSame(10, strlen($sampleId));
        $this->assertNotEquals(0, $sampleId[0]);
    }
}
