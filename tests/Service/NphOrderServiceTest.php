<?php

namespace App\Tests\Service;

use App\Entity\NphOrder;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\SiteService;
use App\Service\UserService;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;

class NphOrderServiceTest extends ServiceTestCase
{
    protected $service;
    protected $em;
    protected $module1Data;
    protected testSetup $testSetup;

    public function setUp(): void
    {
        parent::setUp();
        $this->program = 'nph';
        $this->login('test-nph-user1@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service =  new NphOrderService(
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(SiteService::class),
            $this->createMock(LoggerService::class)
        );
        $this->testSetup = new testSetup(static::getContainer()->get(EntityManagerInterface::class));
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
            ['30min', 'blood', 'LIHP1', '6 mL Lithium Heparin'],
        ];
    }

    /**
     * @dataProvider sampleLabelsAndIdsDataProvider
     */
    public function testGetSamplesWithLabelsAndIds($timePoint, $orderType, $sampleCode, $sampleLabel, $sampleId, $sampleGroup): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000003');
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
            ['30min', 'blood', 'LIHP1', '6 mL Lithium Heparin', '1000000006', '2000000001'],
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
        $nphSample = $this->service->createSample('SALIVA', $nphOrder, '1000000002');

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
        $this->service->loadModules(1, 'LMT', 'P0000000008');
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
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $notes,
        $metaData = []
    ): void {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008');
        if ($orderType === 'stool') {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000005', 'T0000000001');
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000005');
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

    /**
     * @dataProvider orderFinalizationFormDataProvider
     */
    public function testHasAtLeastOneAliquotSample($sampleCode, $formData, $isAtLeastOneSampleChecked): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008');
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
    public function testSaveOrderFinalization(
        $timePoint,
        $orderType,
        $sampleCode,
        $collectedTs,
        $aliquots,
        $aliquotId,
        $duplicate): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000008');

        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000006');

        $finalizedFormData = [
            "{$sampleCode}CollectedTs" => $collectedTs,
            "{$sampleCode}Notes" => 'Test',
        ];
        foreach ($aliquots as $aliquotCode => $aliquot) {
            $finalizedFormData[$aliquotCode][] = $aliquot[0];
            $finalizedFormData["{$aliquotCode}AliquotTs"][] = $aliquot[1];
            $finalizedFormData["{$aliquotCode}Volume"][] = floatval($aliquot[2]);
        }
        $this->service->saveOrderFinalization($finalizedFormData, $nphSample);
        $this->assertSame($collectedTs, $nphSample->getCollectedTs());
        $this->assertSame($finalizedFormData, $this->service->getExistingSampleData($nphSample));

        $finalizedFormData = [];
        foreach ($aliquots as $aliquotCode => $aliquot) {
            $finalizedFormData[$aliquotCode][] = $aliquotId;
        }
        $this->assertSame($duplicate, (bool) $this->service->checkDuplicateAliquotId($finalizedFormData, $sampleCode));

    }

    public function orderFinalizationDataProvider(): array
    {
        $collectedTs = new \DateTime('2022-12-01');
        $aliquotTs = new \DateTime('2022-12-02');
        return [
            ['preLMT', 'urine', 'URINES', $collectedTs, [
                'URINESA1' => ['10001', $aliquotTs, 500],
                'URINESA2' => ['10002', $aliquotTs, 5]
            ], '10001', true],
            ['preLMT', 'saliva', 'SALIVA', $collectedTs, [
                'SALIVAA1' => ['10003', $aliquotTs, 4]
            ], '10008', false],
            ['30min', 'blood', 'SST8P5', $collectedTs, [
                'SST8P5A1' => ['10004', $aliquotTs, 500],
                'SST8P5A2' => ['10005', $aliquotTs, 1000]
            ], '10005', true]
        ];
    }

    public function testGetParticipantOrderSummary(): void
    {
        $orderSummary = $this->service->getParticipantOrderSummary('P0000000001');
        $this->assertSame(array('order' => array(), 'sampleCount' => 0), $orderSummary);
        $participant = $this->testSetup->generateParticipant();
        $this->testSetup->generateNPHOrder($participant, self::getContainer()->get(UserService::class)->getUserEntity(), self::getContainer()->get(SiteService::class));
        $orderSummary = $this->service->getParticipantOrderSummary($participant->id);
        $this->assertIsArray($orderSummary);
        $this->assertArrayHasKey('order', $orderSummary);
        $this->assertSame(count($orderSummary['order']), 1);
        $this->assertArrayHasKey('sampleName', $orderSummary['order']['1']['LMT']['preLMT']['urine']['URINES']);

    }

    public function testGetParticipantOrderSummaryByVisitAndModule(): void
    {
        $participant = $this->testSetup->generateParticipant();
        $this->testSetup->generateNPHOrder($participant, self::getContainer()->get(UserService::class)->getUserEntity(), self::getContainer()->get(SiteService::class));
        $orderSummary = $this->service->getParticipantOrderSummaryByModuleAndVisit($participant->id, '1', 'LMT');
        $this->assertIsArray($orderSummary);
        $this->assertArrayHasKey('order', $orderSummary);
        $this->assertSame(count($orderSummary['order']), 1);
        $this->assertArrayHasKey('sampleName', $orderSummary['order']['preLMT']['urine']['URINES']);
    }

    public function testGetSamplesWithStatus(): void
    {
        // Module 1
        $this->service->loadModules(1, 'LMT', 'P0000000010');
        $this->service->createOrdersAndSamples($this->module1Data['formData']);

        $nphOrders = $this->em->getRepository(NphOrder::class)->findBy([
            'participantId' => 'P0000000010', 'visitType' => 'LMT'
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
        $this->service->loadModules(1, 'LMT', 'P0000000008');
        if ($orderType === 'stool') {
            $nphOrder = $this->service->createOrder($timePoint, $orderType, 'KIT-000000001');
            $this->service->createSample($sampleCode, $nphOrder, '1000000007', 'T0000000001');
        } else {
            $nphOrder = $this->service->createOrder($timePoint, $orderType);
            $this->service->createSample($sampleCode, $nphOrder, '1000000007');
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
            'bowelQuality' => 'normal'
        ];
        $expectedUrineMetaData = [
            'urineColor' => 'Color 1',
            'urineClarity' => 'Clean'
        ];
        $expectedStoolMetaData = [
            'bowelType' => 'I was constipated (had difficulty passing stool), and my stool looks like Type 1 and/or 2',
            'bowelQuality' => 'I tend to have normal formed stool - Type 3 and 4'
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
        $this->service->loadModules(1, 'LMT', 'P0000000010');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000008');
        $modificationFormData = [
            $sampleCode => true,
            'reason' => $modifyReason
        ];
        $nphOrder = $this->service->saveSamplesModification($modificationFormData, $modifyType, $nphOrder);

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
        $this->service->loadModules(1, 'LMT', 'P0000000010');
        $nphOrder = $this->service->createOrder($timePoint, $orderType);
        $nphSample = $this->service->createSample($sampleCode, $nphOrder, '1000000009');

        $finalizedFormData = [
            "{$sampleCode}CollectedTs" => $collectedTs,
            "{$sampleCode}Notes" => 'Test',
        ];
        $this->service->saveOrderFinalization($finalizedFormData, $nphSample);

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
}
