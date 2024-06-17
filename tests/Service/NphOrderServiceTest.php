<?php

namespace App\Tests\Service;

use App\Entity\NphDlw;
use App\Entity\NphOrder;
use App\Helper\NphParticipant;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;

class NphOrderServiceTest extends ServiceTestCase
{
    protected $service;
    protected $em;
    protected array $module1Data;
    protected array $module2Data;
    protected array $module3Data;
    protected testSetup $testSetup;

    public function setUp(): void
    {
        parent::setUp();
        $this->program = 'nph';
        $this->login('test-nph-user1@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $data = $this->getMockRdrResponseData();
        $mockRdrApiService->method('post')->willReturn($this->getGuzzleResponse($data));
        $mockRdrApiService->method('put')->willReturn($this->getGuzzleResponse($data));
        $this->service = new NphOrderService(
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(SiteService::class),
            $this->createMock(LoggerService::class),
            $mockRdrApiService
        );
        $this->testSetup = new testSetup(static::getContainer()->get(EntityManagerInterface::class));
        $this->em = static::$container->get(EntityManagerInterface::class);
        // Module 1
        $this->module1Data = json_decode(file_get_contents(__DIR__ . '/data/order_module_1.json'), true);
        $this->module1Data['formData']['createdTs'] = new \DateTime($this->module1Data['formData']['createdTs']);
        // Module 2
        $this->module2Data = json_decode(file_get_contents(__DIR__ . '/data/order_module_2.json'), true);
        $this->module2Data['formData']['createdTs'] = new \DateTime($this->module2Data['formData']['createdTs']);
        // Module 3
        $this->module3Data = json_decode(file_get_contents(__DIR__ . '/data/order_module_3.json'), true);
        $this->module3Data['formData']['createdTs'] = new \DateTime($this->module3Data['formData']['createdTs']);
    }

    public function testLoadModules(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000001', 'T10000000');
        $this->assertSame($this->module1Data['timePointSamples'], $this->service->getTimePointSamples());
        $this->assertSame($this->module1Data['timePoints'], $this->service->getTimePoints());
        $this->assertSame($this->module1Data['samples'], $this->service->getSamples());

        $this->assertSame($this->module1Data['stoolSamples'], $this->service->getSamplesByType('stool'));
        $this->assertSame($this->module1Data['bloodSamples'], $this->service->getSamplesByType('blood'));
        $this->assertSame($this->module1Data['nailSamples'], $this->service->getSamplesByType('nail'));

        $this->service->loadModules(2, 'Period1DSMT', 'P0000000001', 'T10000000');
        $this->assertSame($this->module2Data['timePointSamples'], $this->service->getTimePointSamples());
        $this->assertSame($this->module2Data['timePoints'], $this->service->getTimePoints());
        $this->assertSame($this->module2Data['samples'], $this->service->getSamples());
        $this->assertSame('PERIOD1', $this->service->getVisitDiet());

        $this->assertSame($this->module2Data['stoolSamples'], $this->service->getSamplesByType('stool'));
        $this->assertSame($this->module2Data['bloodSamples'], $this->service->getSamplesByType('blood'));

        $this->service->loadModules(3, 'Period1DSMT', 'P0000000001', 'T10000000');
        $this->assertSame($this->module3Data['timePointSamples'], $this->service->getTimePointSamples());
        $this->assertSame($this->module3Data['timePoints'], $this->service->getTimePoints());
        $this->assertSame($this->module3Data['samples'], $this->service->getSamples());
        $this->assertSame('PERIOD1', $this->service->getVisitDiet());

        $this->assertSame($this->module3Data['stoolSamples'], $this->service->getSamplesByType('stool'));
        $this->assertSame($this->module3Data['bloodSamples'], $this->service->getSamplesByType('blood'));
    }

    /**
     * @dataProvider sampleTypeDataProvider
     */
    public function testGetSampleType($sampleType, $sampleCode): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000002', 'T10000000');
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
        $this->service->loadModules(1, 'LMT', 'P0000000003', 'T10000000');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $sampleGroup = $this->service->generateSampleGroup();
        $this->service->createSample($sampleCode, $nphOrder, $sampleGroup);
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
            ['30min', 'blood', 'LIHP1', '6 mL Li Hep'],
        ];
    }

    /**
     * @dataProvider sampleLabelsAndIdsDataProvider
     */
    public function testGetSamplesWithLabelsAndIds($timePoint, $orderType, $sampleCode, $sampleLabel, $sampleId, $sampleGroup): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000003', 'T10000000');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $this->service->createSample($sampleCode, $nphOrder, $sampleGroup, $sampleId);
        $expectedSampleLabelAndIds[$sampleCode] = [
            'label' => $sampleLabel,
            'id' => $sampleId,
            'disabled' => false
        ];
        $this->assertSame($expectedSampleLabelAndIds, $this->service->getSamplesWithLabelsAndIds($nphOrder->getNphSamples()));
    }

    public function sampleLabelsAndIdsDataProvider(): array
    {
        return [
            ['preLMT', 'urine', 'URINES', 'Spot Urine', '1000000001', '2000000001'],
            ['preLMT', 'saliva', 'SALIVA', 'Saliva', '1000000002', '2000000001'],
            ['preLMT', 'nail', 'NAILB', 'Big Toenails', '1000000003', '2000000001'],
            ['preLMT', 'stool', 'ST1', '95% Ethanol Tube 1', '1000000004', '2000000001'],
            ['30min', 'blood', 'SST8P5', '8.5 mL SST', '1000000005', '2000000001'],
            ['30min', 'blood', 'LIHP1', '6 mL Li Hep', '1000000006', '2000000001'],
        ];
    }

    public function testCreateOrder()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000004', 'T10000000');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $this->assertSame('LMT', $nphOrder->getVisitPeriod());
        $this->assertSame('preLMT', $nphOrder->getTimepoint());
        $this->assertSame('saliva', $nphOrder->getOrderType());
    }

    public function testCreateSample()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000005', 'T10000000');
        $nphOrder = $this->service->createOrder('preLMT', 'saliva');
        $nphSample = $this->service->createSample('SALIVA', $nphOrder, '1000000002');

        $this->assertSame('SALIVA', $nphSample->getSampleCode());
    }

    public function testCreateOrdersAndSamples()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000006', 'T10000000');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);
        $orderData = $this->module1Data['formData'];
        unset($orderData['createdTs'], $orderData['downtime_generated']);
        $this->assertSame($orderData, $this->service->getExistingOrdersData());
    }

    public function testGetSamplesWithOrderIds()
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000007', 'T10000000');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);

        $nphOrders = $this->em->getRepository(NphOrder::class)->findBy([
            'participantId' => 'P0000000007', 'visitPeriod' => 'LMT'
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
                        $this->assertSame($nphOrder->getOrderId(), $samplesWithOrderIds[$timePoint][$sampleCode]['orderId']);
                    }
                }
            }
        }
    }

    /**
     * @dataProvider orderCollectionFormDataProvider
     */
    public function testIsAtLeastOneSampleChecked(
        $timePoint,
        $orderType,
        $sampleCodes,
        $formData,
        $isAtLeastOneSampleChecked
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008', 'T10000000');
        if ($orderType === 'stool') {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            foreach ($sampleCodes as $key => $sampleCode) {
                $this->service->createSample($sampleCode, $nphOrder, '1000000003', "T000000000{$key}");
            }
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            foreach ($sampleCodes as $sampleCode) {
                $this->service->createSample($sampleCode, $nphOrder, '1000000003');
            }
        }
        $this->assertSame($this->service->isAtLeastOneSampleChecked($formData, $nphOrder), $isAtLeastOneSampleChecked);
    }

    public function orderCollectionFormDataProvider(): array
    {
        return [
            ['preLMT', 'urine', ['URINES'], ['URINES' => true], true],
            ['preLMT', 'saliva', ['SALIVA'], ['SALIVA' => true], true],
            ['preLMT', 'stool', ['ST1', 'ST2'], ['ST1' => false, 'ST2' => true], true],
            ['30min', 'blood', ['SST8P5', 'PST8'], ['SST8P5' => false, 'PST8' => false], false],
            ['postLMT', 'saliva', ['SALIVA'], ['SALIVA' => false], false],
        ];
    }

    /**
     * @dataProvider orderCollectionDataProvider
     */
    public function testSaveOrderCollection(
        string $timePoint,
        string $orderType,
        string $sampleCode,
        \DateTime $collectedTs,
        string $notes,
        int $module,
        string $visit,
        array $metaData = [],
    ): void {
        // Module 1
        $this->service->loadModules($module, $visit, 'P0000000008', 'T10000000');
        if ($orderType === NphOrder::TYPE_STOOL) {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000005', 'T0000000001');
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000005');
        }
        $collectedField = $orderType === NphOrder::TYPE_STOOL ? $orderType . 'CollectedTs' : $sampleCode . 'CollectedTs';
        $collectionFormData = [
            $collectedField => $collectedTs,
            $sampleCode => true,
            "{$sampleCode}Notes" => $notes,
        ];
        if ($orderType === NphOrder::TYPE_URINE || $orderType === NphOrder::TYPE_STOOL) {
            foreach ($metaData as $type => $data) {
                $collectionFormData[$type] = $data;
            }
        }
        $this->service->saveOrderCollection($collectionFormData, $nphOrder);
        $this->assertSame($collectedTs, $nphSample->getCollectedTs());
        $this->assertSame($notes, $nphSample->getCollectedNotes());
        $this->assertSame($collectionFormData, $this->service->getExistingOrderCollectionData($nphOrder));

        if ($metaData) {
            $this->assertSame(json_encode($metaData), $this->service->jsonEncodeMetadata(
                $collectionFormData,
                array_keys($metaData)
            ));
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
            ['preLMT', 'urine', 'URINES', $collectedTs, 'Test Notes 1', 1, 'LMT', $urineMetaData],
            ['preLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 2', 1, 'LMT'],
            ['preLMT', 'stool', 'ST1', $collectedTs, 'Test Notes 3', 1, 'LMT', $stoolMetaData],
            ['30min', 'blood', 'SST8P5', $collectedTs, 'Test Notes 4', 1, 'LMT'],
            ['postLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 5', 1, 'LMT'],
            ['preDSMT', 'blood', 'LIH4', $collectedTs, 'Test Notes 7', 3, 'Period1DSMT'],
            ['postDSMT', 'urine', 'URINE', $collectedTs, 'Test Notes 8', 3, 'Period1DSMT', $urineMetaData]
        ];
    }

    public function testGenerateOrderAndSampleIds(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008', 'T10000000');

        $orderId = $this->service->generateOrderId();
        $this->assertSame(10, strlen($orderId));
        $this->assertNotEquals(0, $orderId[0]);

        $sampleId = $this->service->generateSampleId();
        $this->assertSame(10, strlen($sampleId));
        $this->assertNotEquals(0, $sampleId[0]);
    }

    /**
     * @dataProvider orderFinalizationFormDataProvider
     */
    public function testHasAtLeastOneAliquotSample($sampleCode, $formData, $isAtLeastOneSampleChecked): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008', 'T10000000');
        $this->assertSame($this->service->hasAtLeastOneAliquotSample($formData, $sampleCode), $isAtLeastOneSampleChecked);
    }

    public function orderFinalizationFormDataProvider(): array
    {
        return [
            ['URINES', ['URINESA1' => ['1234567890'], ['URINESA2' => []]], true],
            ['URINES', ['URINESA1' => []], false],
            ['LIHP1', ['LIHP1A1' => ['1234567890']], true],
            ['LIHP1', ['LIHP1A1' => []], false],
            ['SALIVA', ['SALIVAA1' => ['1234567890']], true],
            ['SALIVA', [], false]
        ];
    }

    /**
     * @dataProvider orderFinalizationDataProvider
     */
    public function testSaveFinalization(
        string $timePoint,
        string $orderType,
        string $sampleCode,
        string $sampleIdentifier,
        \DateTime $collectedTs,
        array $aliquots,
        string $aliquotId,
        bool $duplicate,
        bool $hasDuplicatesInform,
        string $expectedRdrTimePoint,
        bool $biobankFinalized,
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008', 'T10000000');

        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000006');

        $finalizedFormData = [
            "{$sampleCode}CollectedTs" => $collectedTs,
            "{$sampleCode}Notes" => 'Test',
        ];
        if ($aliquots) {
            foreach ($aliquots as $aliquotCode => $aliquot) {
                $finalizedFormData[$aliquotCode][] = $aliquot[0];
                $finalizedFormData["{$aliquotCode}AliquotTs"][] = $aliquot[1];
                $finalizedFormData["{$aliquotCode}Volume"][] = floatval($aliquot[2]);
            }
            $this->assertSame($hasDuplicatesInform, $this->service->hasDuplicateAliquotsInForm($finalizedFormData, $sampleCode));
        }
        $this->service->saveFinalization($finalizedFormData, $nphSample, $biobankFinalized);
        $this->assertSame($collectedTs, $nphSample->getCollectedTs());
        $this->assertSame($finalizedFormData, $this->service->getExistingSampleData($nphSample));
        $this->assertSame($biobankFinalized, $nphSample->getBiobankFinalized());

        if ($aliquots) {
            $finalizedFormData = [];
            foreach ($aliquots as $aliquotCode => $aliquot) {
                $finalizedFormData[$aliquotCode][] = $aliquotId;
            }
            $this->assertSame($duplicate, (bool) $this->service->checkDuplicateAliquotId($finalizedFormData, $sampleCode));
        }

        // Test RDR Object
        $rdrObject = $this->service->getRdrObject($nphOrder, $nphSample);
        $this->assertEquals('Patient/P0000000008', $rdrObject->subject);
        // Assert module info
        $this->assertEquals(1, $rdrObject->module);
        $this->assertEquals('LMT', $rdrObject->visitPeriod);
        $this->assertEquals($expectedRdrTimePoint, $rdrObject->timepoint);
        // Assert identifiers orderId and sampleId
        $this->assertEquals($nphOrder->getOrderId(), $rdrObject->identifier[0]['value']);
        $this->assertEquals($nphSample->getSampleId(), $rdrObject->identifier[1]['value']);
        // Assert createdInfo
        $this->assertEquals('test-nph-user1@example.com', $rdrObject->createdInfo['author']['value']);
        $this->assertEquals('nph-site-test', $rdrObject->createdInfo['site']['value']);
        // Assert sample code
        $this->assertEquals($sampleIdentifier, $rdrObject->sample['test']);
    }

    public function orderFinalizationDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-12-01');
        $aliquotTs = new \DateTime('2022-12-02');
        return [
            ['preLMT', 'urine', 'URINES', 'UrineS', $collectedTs, [
                'URINESA1' => ['10001', $aliquotTs, 500],
                'URINESA2' => ['10002', $aliquotTs, 5]
            ], '10001', true, false, 'Pre LMT', false],
            ['preLMT', 'saliva', 'SALIVA', 'Saliva', $collectedTs, [
                'SALIVAA1' => ['10003', $aliquotTs, 4]
            ], '10008', false, false, 'Pre LMT', false],
            ['30min', 'blood', 'SST8P5', 'SST8.5', $collectedTs, [
                'SST8P5A1' => ['10004', $aliquotTs, 500],
                'SST8P5A2' => ['10005', $aliquotTs, 1000]
            ], '10005', true, false, '30 min', false],
            ['preLMT', 'stool', 'ST1', 'ST1-K', $collectedTs, [], '10006', true, false, 'Pre LMT', false],
            ['preLMT', 'stool', 'ST1', 'ST1-K', $collectedTs, [], '10006', true, false, 'Pre LMT', true],
        ];
    }

    public function testGetParticipantOrderSummary(): void
    {
        $orderSummary = $this->service->getParticipantOrderSummary('P0000000001');
        $this->assertSame(['order' => [], 'sampleCount' => 0, 'sampleStatusCount' => []], $orderSummary);
        $participant = $this->testSetup->generateNphParticipant();
        $this->testSetup->generateNPHOrder($participant, self::getContainer()->get(UserService::class)->getUserEntity(), self::getContainer()->get(SiteService::class));
        $orderSummary = $this->service->getParticipantOrderSummary($participant->id);
        $this->assertIsArray($orderSummary);
        $this->assertArrayHasKey('order', $orderSummary);
        $this->assertSame(count($orderSummary['order']), 1);
        $this->assertArrayHasKey('sampleName', $orderSummary['order']['1']['LMT']['preLMT']['urine']['URINES']['100000001']);
    }

    public function testGetParticipantOrderSummaryByVisitAndModule(): void
    {
        $participant = $this->testSetup->generateNphParticipant();
        $this->testSetup->generateNPHOrder($participant, self::getContainer()->get(UserService::class)->getUserEntity(), self::getContainer()->get(SiteService::class));
        $orderSummary = $this->service->getParticipantOrderSummaryByModuleAndVisit($participant->id, '1', 'LMT');
        $this->assertIsArray($orderSummary);
        $this->assertArrayHasKey('order', $orderSummary);
        $this->assertSame(count($orderSummary['order']), 1);
        $this->assertArrayHasKey('sampleName', $orderSummary['order']['preLMT']['urine']['URINES']['100000001']);
    }

    public function testGetSamplesWithStatus(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000010', 'T10000000');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);

        $nphOrders = $this->em->getRepository(NphOrder::class)->findBy([
            'participantId' => 'P0000000010', 'visitPeriod' => 'LMT'
        ]);
        $samplesWithStatus = $this->service->getSamplesWithStatus();
        $timePointSamples = [
            ['preLMT', 'saliva', 'SALIVA'],
            ['preLMT', 'urine', 'URINES'],
            ['preLMT', 'nail', 'NAILB'],
            ['30min', 'blood', 'SST8P5'],
        ];
        foreach ($nphOrders as $nphOrder) {
            foreach ($timePointSamples as $timePointSample) {
                list($timePoint, $sampleType, $sampleCode) = $timePointSample;
                if ($nphOrder->getTimepoint() === $timePoint) {
                    if ($nphOrder->getOrderType() === $sampleType) {
                        $this->assertSame($nphOrder->getNphSamples()[0]->getStatus(), $samplesWithStatus[$timePoint][$sampleCode]);
                    }
                }
            }
        }
    }
    /**
     * @dataProvider samplesMetadataDataProvider
     */
    public function testGetSamplesMetadata(
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $notes,
        $metaData,
        $expectedMetaData
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008', 'T10000000');
        if ($orderType === 'stool') {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            $this->service->createSample($sampleCode, $nphOrder, '1000000007', 'T0000000001');
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            $this->service->createSample($sampleCode, $nphOrder, '1000000007');
        }
        $collectedField = $orderType === NphOrder::TYPE_STOOL ? $orderType . 'CollectedTs' : $sampleCode . 'CollectedTs';
        $collectionFormData = [
            $sampleCode => true,
            $collectedField => $collectedTs,
            "{$sampleCode}Notes" => $notes,
        ];
        if ($orderType === NphOrder::TYPE_URINE || $orderType === NphOrder::TYPE_STOOL) {
            foreach ($metaData as $type => $data) {
                $collectionFormData[$type] = $data;
            }
        }
        $this->service->saveOrderCollection($collectionFormData, $nphOrder);
        $this->assertSame($this->service->getSamplesMetadata($nphOrder), $expectedMetaData);
    }

    public function samplesMetadataDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-11-18');
        $urineMetaData = [
            'urineColor' => 1,
            'urineClarity' => 'clean'
        ];
        $stoolMetaData = [
            'bowelType' => 'difficult',
            'bowelQuality' => 'normal',
            'freezedTs' => null
        ];
        $expectedUrineMetaData = [
            'urineColor' => 'Color 1',
            'urineClarity' => 'Clean'
        ];
        $expectedStoolMetaData = [
            'bowelType' => 'I was constipated (had difficulty passing stool), and my stool looks like Type 1 and/or 2',
            'bowelQuality' => 'I tend to have normal formed stool - Type 3 and 4',
            'freezedTs' => null
        ];
        return [
            ['preLMT', 'urine', 'URINES', $collectedTs, 'Test Notes 1', $urineMetaData, $expectedUrineMetaData],
            ['preLMT', 'stool', 'ST1', $collectedTs, 'Test Notes 3', $stoolMetaData, $expectedStoolMetaData]
        ];
    }

    /**
     * @dataProvider saveSamplesModificationDataProvider
     */
    public function testSaveSamplesModification(
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $notes,
        $modifyReason,
        $modifyType
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000010', 'T10000000');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000008');
        $modificationFormData = [
            $sampleCode => true,
            'reason' => $modifyReason
        ];
        $this->service->saveSamplesModification($modificationFormData, $modifyType, $nphOrder);

        foreach ($nphOrder->getNphSamples() as $sample) {
            if ($sample->getSampleCode() === $sampleCode) {
                $this->assertSame($modifyReason, $nphSample->getModifyReason());
            }
        }
    }

    public function saveSamplesModificationDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-11-18');
        return [
            ['preLMT', 'urine', 'URINES', $collectedTs, 'Test Notes 1', 'SAMPLE_CANCEL_ERROR', 'cancel'],
            ['preLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 2', 'SAMPLE_CANCEL_WRONG_PARTICIPANT', 'cancel'],
            ['30min', 'blood', 'SST8P5', $collectedTs, 'Test Notes 4', 'SAMPLE_CANCEL_LABEL_ERROR', 'cancel'],
            ['postLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 5', 'SAMPLE_CANCEL_ERROR', 'cancel'],
        ];
    }

    /**
     * @dataProvider saveSampleModificationDataProvider
     */
    public function testSaveSampleModification(
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $notes,
        $modifyReason,
        $modifyType
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000010', 'T10000000');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000009');

        $finalizedFormData = [
            "{$sampleCode}CollectedTs" => $collectedTs,
            "{$sampleCode}Notes" => 'Test',
        ];
        $this->service->saveFinalization($finalizedFormData, $nphSample);

        $modificationFormData = [
            'reason' => $modifyReason
        ];
        $nphSample = $this->service->saveSampleModification($modificationFormData, $modifyType, $nphSample);
        $this->assertSame($modifyReason, $nphSample->getModifyReason());
    }

    public function saveSampleModificationDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-11-18');
        return [
            ['preLMT', 'urine', 'URINES', $collectedTs, 'Test Notes 1', 'CHANGE_COLLECTION_INFORMATION', 'unlock'],
            ['preLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 2', 'CHANGE_ADD_REMOVE_ALIQUOT', 'unlock'],
            ['30min', 'blood', 'SST8P5', $collectedTs, 'Test Notes 4', 'CHANGE_COLLECTION_INFORMATION', 'unlock'],
            ['postLMT', 'saliva', 'SALIVA', $collectedTs, 'Test Notes 5', 'CHANGE_ADD_REMOVE_ALIQUOT', 'unlock'],
        ];
    }

    /**
     * @dataProvider sampleLabelsAndIdsDataProvider
     */
    public function testGetParticipantOrderSummaryByModuleVisitAndSampleGroup($timePoint, $orderType, $sampleCode, $sampleLabel, $sampleId, $sampleGroup): void
    {
        $this->service->loadModules(1, 'LMT', 'P0000000003', 'T10000000');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $this->service->createSample($sampleCode, $nphOrder, $sampleGroup, $sampleId);
        $orderSummary = $this->service->getParticipantOrderSummaryByModuleVisitAndSampleGroup('P0000000003', 1, 'LMT', $sampleGroup);
        $this->service->getSamples();
        $this->assertCount($orderSummary['sampleCount'], $orderSummary['order']);
        $this->assertSame(1, $orderSummary['sampleCount']);
        $this->assertIsArray($orderSummary);
    }

    /**
     * @dataProvider validateGenerateOrdersDataProvider
     */
    public function testValidateGenerateOrdersData($formData, $expectedFormErrors): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000010', 'T10000000');
        $nphOrder = $this->service->createOrder('preLMT', 'stool', 'KIT-00000001');
        $this->service->createSample('ST1', $nphOrder, '1000000008', '00000000001');
        $this->assertSame($expectedFormErrors, $this->service->validateGenerateOrdersData($formData));
    }

    public function validateGenerateOrdersDataProvider(): array
    {
        return [
            [
                ['stoolKit' => 'KIT-00000001', 'ST1' => '00000000002'],
                [['field' => 'stoolKit', 'message' => 'This Kit ID has already been used for another order']]
            ],
            [
                ['stoolKit' => 'KIT-00000002', 'ST1' => '00000000001'],
                [['field' => 'ST1', 'message' => 'This Tube ID has already been used for another sample']]
            ],
            [
                ['checkAll'],
                [['field' => 'checkAll', 'message' => 'Please select or enter at least one sample']]
            ],
            [
                ['stoolKit' => 'KIT-00000004', 'ST1' => '00000000003', 'ST2' => '00000000003'],
                [['field' => 'checkAll', 'message' => 'Please enter unique Stool Tube IDs']]
            ],
        ];
    }

    /**
     * @dataProvider dietStartedDataProvider
     */
    public function testIsDietStarted(array $moduleDietStatus, bool $expectedResult): void
    {
        $this->service->loadModules(2, 'Period1Diet', 'P0000000010', 'T10000000');
        $actualResult = $this->service->isDietStarted($moduleDietStatus);
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dietStartedDataProvider(): array
    {
        return [
            [
                ['PERIOD1' => NphParticipant::DIET_STARTED],
                true,
            ],
            [
                ['PERIOD1' => NphParticipant::DIET_COMPLETED],
                false,
            ],
            [
                ['PERIOD1' => NphParticipant::DIET_DISCONTINUED],
                false,
            ],
        ];
    }

    /**
     * @dataProvider dietStartedOrCompletedDataProvider
     */
    public function testIsDietStartedOrCompleted(array $moduleDietStatus, bool $expectedResult): void
    {
        $this->service->loadModules(2, 'Period1Diet', 'P0000000010', 'T10000000');
        $actualResult = $this->service->isDietStartedOrCompleted($moduleDietStatus);
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dietStartedOrCompletedDataProvider(): array
    {
        return [
            [
                ['PERIOD1' => NphParticipant::DIET_STARTED],
                true,
            ],
            [
                ['PERIOD1' => NphParticipant::DIET_COMPLETED],
                true,
            ],
            [
                ['PERIOD1' => NphParticipant::DIET_DISCONTINUED],
                false,
            ],
        ];
    }

    public function saveDlwCollectionDataProvider(): array
    {
        return [
            ['P0000000003', 1, 'Period1Diet', [
                'actualDose' => 100.0,
                'participantWeight' => 120.1,
                'collectionDate' => new \DateTime(),
            ]],
        ];
    }

    /**
     * @dataProvider saveDlwCollectionDataProvider
     */
    public function testSaveDlwCollection($participantId, $module, $visit, $formData)
    {
        $dlw = new NphDlw();
        $dlw->setActualDose($formData['actualDose']);
        $dlw->setParticipantWeight($formData['participantWeight']);
        $dlw->setDoseAdministered($formData['collectionDate']);
        $savedDlw = $this->service->saveDlwCollection($dlw, $participantId, $module, $visit);
        $this->assertSame($dlw->getActualDose(), $savedDlw->getActualDose());
        $this->assertSame($dlw->getParticipantWeight(), $savedDlw->getParticipantWeight());
        $this->assertSame($dlw->getUser(), $savedDlw->getUser());
    }

    public function testGenerateDlwSummary()
    {
        $dlw = $this->testSetup->generateNphDlw(self::getContainer()->get(UserService::class)->getUserEntity());
        $dlwRepository = $this->em->getRepository(NphDlw::class)->findOneBy(['id' => $dlw->getId()]);
        $dlwSummary = $this->service->generateDlwSummary([$dlwRepository]);
        $this->assertArrayHasKey($dlw->getModule(), $dlwSummary);
        $this->assertArrayHasKey($dlw->getVisitPeriod(), $dlwSummary[$dlw->getModule()]);
        $this->assertEquals($dlw->getDoseAdministered(), $dlwSummary[$dlw->getModule()][$dlw->getVisitPeriod()]);
    }

    /**
     * @dataProvider activeDietPeriodProvider
     */
    public function testGetActiveDietPeriod(array $moduleDietPeriodStatus, string $currentModule, string $expectedResult)
    {
        $result = $this->service->getActiveDietPeriod($moduleDietPeriodStatus, $currentModule);
        $this->assertEquals($expectedResult, $result);
    }

    public function activeDietPeriodProvider(): array
    {
        return [
            'no in-progress periods' => [
                'moduleDietPeriodStatus' => [
                    1 => ['LMT' => 'error_next_module_started'],
                    2 => ['Period1' => 'not_started', 'Period2' => 'not_started', 'Period3' => 'not_started'],
                    3 => ['Period1' => 'error_next_diet_started', 'Period2' => 'error_next_diet_started', 'Period3' => 'in_progress_unfinalized']
                ],
                'currentModule' => '2',
                'expectedResult' => 'Period1'
            ],
            'in-progress period exists' => [
                'moduleDietPeriodStatus' => [
                    1 => ['LMT' => 'error_next_module_started'],
                    2 => ['Period1' => 'error_next_diet_started', 'Period2' => 'in_progress_unfinalized', 'Period3' => 'not_started'],
                    3 => ['Period1' => 'error_next_diet_started', 'Period2' => 'error_next_diet_started', 'Period3' => 'in_progress_unfinalized']
                ],
                'currentModule' => '2',
                'expectedResult' => 'Period2'
            ],
            'all periods unfinalized' => [
                'moduleDietPeriodStatus' => [
                    1 => ['LMT' => 'error_next_module_started'],
                    2 => ['Period1' => 'in_progress_unfinalized', 'Period2' => 'in_progress_unfinalized', 'Period3' => 'in_progress_unfinalized'],
                    3 => ['Period1' => 'error_next_diet_started', 'Period2' => 'error_next_diet_started', 'Period3' => 'in_progress_unfinalized']
                ],
                'currentModule' => '2',
                'expectedResult' => 'Period1'
            ]
        ];
    }

    /**
     * @dataProvider activeModuleProvider
     */
    public function testGetActiveModule(array $moduleDietPeriodStatus, string $currentModule, $expectedResult)
    {
        $result = $this->service->getActiveModule($moduleDietPeriodStatus, $currentModule);
        $this->assertEquals($expectedResult, $result);
    }

    public function activeModuleProvider(): array
    {
        return [
            'in progress module 1' => [
                'moduleDietPeriodStatus' => [
                    1 => ['LMT' => 'in_progress_unfinalized'],
                    2 => ['Period1' => 'not_started', 'Period2' => 'not_started', 'Period3' => 'not_started'],
                    3 => ['Period1' => 'error_next_diet_started', 'Period2' => 'error_next_diet_started', 'Period3' => 'in_progress_unfinalized']
                ],
                'currentModule' => '2',
                'expectedResult' => '1'
            ],
            'not started module 1' => [
                'moduleDietPeriodStatus' => [
                    1 => ['LMT' => 'not_started'],
                    2 => ['Period1' => 'not_started', 'Period2' => 'in_progress_unfinalized', 'Period3' => 'not_started'],
                    3 => ['Period1' => 'error_next_diet_started', 'Period2' => 'error_next_diet_started', 'Period3' => 'in_progress_unfinalized']
                ],
                'currentModule' => '2',
                'expectedResult' => '1'
            ]
        ];
    }

    private function getGuzzleResponse($data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $data);
    }

    private function getMockRdrResponseData(): string
    {
        return '{"id": "12345"}';
    }
}
