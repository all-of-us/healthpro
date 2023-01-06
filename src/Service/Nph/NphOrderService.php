<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use App\Form\Nph\NphOrderForm;
use App\Service\LoggerService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;
use Doctrine\ORM\PersistentCollection;

class NphOrderService
{
    private $em;
    private $userService;
    private $siteService;
    private $loggerService;

    private $module;
    private $visit;
    private $moduleObj;
    private $participantId;
    private $user;
    private $site;

    private static $nonBloodTimePoints = ['preLMT', 'postLMT', 'preDSMT', 'postDSMT'];

    private static $placeholderSamples = ['NAIL', 'STOOL'];

    public function __construct(
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService
    ) {
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
    }

    public function loadModules(string $module, string $visit, string $participantId): void
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $this->moduleObj = new $moduleClass($visit);

        $this->module = $module;
        $this->visit = $visit;
        $this->participantId = $participantId;

        $this->user = $this->em->getRepository(User::class)->find($this->userService->getUser()->getId());
        $this->site = $this->siteService->getSiteId();
    }

    public function getTimePointSamples(): array
    {
        return $this->moduleObj->getTimePointSamples();
    }

    public function getTimePoints(): array
    {
        return $this->moduleObj->getTimePoints();
    }

    public function getSamples(): array
    {
        return $this->moduleObj->getSamples();
    }

    public function getSamplesByType(string $type): array
    {
        return $this->moduleObj->getSamplesByType($type);
    }

    public function getSampleType(string $sampleCode): string
    {
        return $this->moduleObj->getSampleType($sampleCode);
    }

    public function getAliquots(string $sampleCode): ?array
    {
        return $this->moduleObj->getAliquots($sampleCode);
    }

    public function getSamplesWithLabels(PersistentCollection $samplesObj): array
    {
        $samples = $this->getSamples();
        $sampleLabels = [];
        foreach ($samplesObj as $sampleObj) {
            $sampleLabels[$sampleObj->getSampleCode()] = $samples[$sampleObj->getSampleCode()];
        }
        return $sampleLabels;
    }

    public function getSamplesWithLabelsAndIds(PersistentCollection $samplesObj): array
    {
        $samples = $this->getSamples();
        $sampleLabels = [];
        foreach ($samplesObj as $sampleObj) {
            $sampleLabels[$sampleObj->getSampleCode()] = [
                'label' => $samples[$sampleObj->getSampleCode()],
                'id' => $sampleObj->getSampleId(),
                'disabled' => $sampleObj->isDisabled()
            ];
        }
        return $sampleLabels;
    }

    public function getExistingOrdersData(): array
    {
        $ordersData = [];
        $orders = $this->em->getRepository(NphOrder::class)->getOrdersByVisitType(
            $this->participantId,
            $this->visit
        );
        $addStoolKit = true;
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                if (in_array($sample->getSampleCode(), $this->getSamplesByType('stool'))) {
                    if ($addStoolKit) {
                        $ordersData['stoolKit'] = $order->getOrderId();
                        $addStoolKit = false;
                    }
                    $ordersData[$sample->getSampleCode()] = $sample->getSampleId();
                } else {
                    $ordersData[$order->getTimepoint()][] = $sample->getSampleCode();
                }
            }
        }
        return $ordersData;
    }

    public function getSamplesWithOrderIds(): array
    {
        $samplesData = [];
        $orders = $this->em->getRepository(NphOrder::class)->getOrdersByVisitType(
            $this->participantId,
            $this->visit
        );
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                $samplesData[$order->getTimepoint()][$sample->getSampleCode()] = [
                    'id' => $order->getId(),
                    'orderId' => $order->getOrderId()
                ];
            }
        }
        return $samplesData;
    }

    public function generateOrderId(): string
    {
        $attempts = 0;
        $nphOrderRepository = $this->em->getRepository(NphOrder::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($nphOrderRepository->findOneBy(['orderId' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (empty($id)) {
            throw new \Exception('Failed to generate unique order id');
        }
        return $id;
    }

    public function generateSampleId(): string
    {
        $attempts = 0;
        $nphSampleRepository = $this->em->getRepository(NphSample::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($nphSampleRepository->findOneBy(['sampleId' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (empty($id)) {
            throw new \Exception('Failed to generate unique sample id');
        }
        return $id;
    }

    private function getNumericId(): string
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string)rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string)rand(0, 9);
        }
        return $id;
    }

    public function createOrdersAndSamples(array $formData): void
    {
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                if (in_array($timePoint, self::$nonBloodTimePoints)) {
                    $nailSamples = [];
                    foreach ($samples as $sample) {
                        if (in_array($sample, $this->getSamplesByType('nail'))) {
                            $nailSamples[] = $sample;
                        } elseif (!in_array($sample, self::$placeholderSamples)) {
                            $nphOrder = $this->createOrder($timePoint, $this->getSampleType($sample));
                            $this->createSample($sample, $nphOrder);
                        }
                    }
                    if (!empty($nailSamples)) {
                        $this->createOrderWithSamples($timePoint, 'nail', $nailSamples);
                    }
                } else {
                    $this->createOrderWithSamples($timePoint, 'blood', $samples);
                }
            }
        }
        // For stool kit samples
        if (!empty($formData['stoolKit'])) {
            // TODO: dynamically load stool visit type
            $nphOrder = $this->createOrder('preLMT', 'stool', $formData['stoolKit']);
            foreach ($this->getSamplesByType('stool') as $stoolSample) {
                $this->createSample($stoolSample, $nphOrder, $formData[$stoolSample]);
            }
        }
    }

    public function createOrder(string $timePoint, string $orderType, string $orderId = null): NphOrder
    {
        if ($orderId === null) {
            $orderId = $this->generateOrderId();
        }
        $nphOrder = new NphOrder();
        $nphOrder->setModule($this->module);
        $nphOrder->setVisitType($this->visit);
        $nphOrder->setTimepoint($timePoint);
        $nphOrder->setOrderId($orderId);
        $nphOrder->setParticipantId($this->participantId);
        $nphOrder->setUser($this->user);
        $nphOrder->setSite($this->site);
        $nphOrder->setCreatedTs(new DateTime());
        $nphOrder->setOrderType($orderType);
        $this->em->persist($nphOrder);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_ORDER_CREATE, $nphOrder->getId());
        return $nphOrder;
    }

    public function createSample(string $sample, NphOrder $nphOrder, string $sampleId = null): NphSample
    {
        if ($sampleId === null) {
            $sampleId = $this->generateSampleId();
        }
        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId($sampleId);
        $nphSample->setSampleCode($sample);
        $this->em->persist($nphSample);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_SAMPLE_CREATE, $nphSample->getId());
        return $nphSample;
    }

    private function createOrderWithSamples(string $timePoint, string $orderType, array $samples): void
    {
        $nphOrder = $this->createOrder($timePoint, $orderType);
        foreach ($samples as $sample) {
            $this->createSample($sample, $nphOrder);
        }
    }

    public function isAtLeastOneSampleChecked(array $formData, NphOrder $order): bool
    {
        foreach ($order->getNphSamples() as $nphSample) {
            if (isset($formData[$nphSample->getSampleCode()]) && $formData[$nphSample->getSampleCode()] === true) {
                return true;
            }
        }
        return false;
    }

    public function saveOrderCollection(array $formData, NphOrder $order): NphOrder
    {
        foreach ($order->getNphSamples() as $nphSample) {
            $sampleCode = $nphSample->getSampleCode();
            $nphSample->setCollectedUser($this->user);
            $nphSample->setCollectedSite($this->site);
            $nphSample->setCollectedTs($formData[$sampleCode . 'CollectedTs']);
            $nphSample->setCollectedNotes($formData[$sampleCode . 'Notes']);
            if ($order->getOrderType() === 'urine') {
                $nphSample->setSampleMetadata($this->jsonEncodeMetadata($formData, ['urineColor',
                    'urineClarity']));
            }
            $this->em->persist($nphSample);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $nphSample->getId());
        }
        if ($order->getOrderType() === 'stool') {
            $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType',
                'bowelQuality']));
            $this->em->persist($order);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_ORDER_UPDATE, $order->getId());
        }
        return $order;
    }

    public function getExistingOrderCollectionData(NphOrder $order): array
    {
        $orderCollectionData = [];
        foreach ($order->getNphSamples() as $nphSample) {
            $sampleCode = $nphSample->getSampleCode();
            if ($nphSample->getCollectedTs()) {
                $orderCollectionData[$sampleCode] = true;
            }
            $orderCollectionData[$sampleCode . 'CollectedTs'] = $nphSample->getCollectedTs();
            $orderCollectionData[$sampleCode . 'Notes'] = $nphSample->getCollectedNotes();
            if ($order->getOrderType() === 'urine') {
                if ($nphSample->getSampleMetaData()) {
                    $sampleMetadata = json_decode($nphSample->getSampleMetaData(), true);
                    if (!empty($sampleMetadata['urineColor'])) {
                        $orderCollectionData['urineColor'] = $sampleMetadata['urineColor'];
                    }
                    if (!empty($sampleMetadata['urineClarity'])) {
                        $orderCollectionData['urineClarity'] = $sampleMetadata['urineClarity'];
                    }
                }
            }
        }
        if ($order->getOrderType() === 'stool') {
            if ($order->getMetadata()) {
                $metadata = json_decode($order->getMetadata(), true);
                if (!empty($metadata['bowelType'])) {
                    $orderCollectionData['bowelType'] = $metadata['bowelType'];
                }
                if (!empty($metadata['bowelQuality'])) {
                    $orderCollectionData['bowelQuality'] = $metadata['bowelQuality'];
                }
            }
        }
        return $orderCollectionData;
    }

    public function jsonEncodeMetadata(array $formData, array $metaDataTypes): ?string
    {
        $metadata = [];
        foreach ($metaDataTypes as $metaDataType) {
            if (!empty($formData[$metaDataType])) {
                $metadata[$metaDataType] = $formData[$metaDataType];
            }
        }
        return !empty($metadata) ? json_encode($metadata) : null;
    }

    public function getSamplesMetadata(NphOrder $order): array
    {
        $metadata = [];
        if ($order->getOrderType() === 'stool') {
            $metadata = json_decode($order->getMetadata(), true);
            $metadata['bowelType'] = $this->mapMetadata($metadata, 'bowelType', NphOrderForm::$bowelMovements);
            $metadata['bowelQuality'] = $this->mapMetadata($metadata, 'bowelQuality', NphOrderForm::$bowelMovementQuality);
            ;
        } elseif ($order->getOrderType() === 'urine') {
            $metadata = json_decode($order->getNphSamples()[0]->getSampleMetadata(), true);
            $metadata['urineColor'] = $this->mapMetadata($metadata, 'urineColor', NphOrderForm::$urineColors);
            $metadata['urineClarity'] = $this->mapMetadata($metadata, 'urineClarity', NphOrderForm::$urineClarity);
        }
        return $metadata;
    }

    private function mapMetadata($metadata, $type, $values): string
    {
        return isset($metadata[$type]) ? array_search($metadata[$type], $values) : '';
    }

    public function getParticipantOrderSummary(string $participantid): array
    {
        $OrderRepository = $this->em->getRepository(NphOrder::class);
        $orderInfo = $OrderRepository->findBy(['participantId' => $participantid]);
        return $this->generateOrderSummaryArray($orderInfo);
    }

    public function getParticipantOrderSummaryByModuleAndVisit(string $participantid, string $module, string $visit): array
    {
        $OrderRepository = $this->em->getRepository(NphOrder::class);
        $orderInfo = $OrderRepository->findBy(['participantId' => $participantid, 'visitType' => $visit, 'module' => $module]);
        $orderSummary = $this->generateOrderSummaryArray($orderInfo);
        $orderSummary['order'] = $orderSummary['order'][$module][$visit];
        return $orderSummary;
    }

    private function generateOrderSummaryArray(array $nphOrder): array
    {
        $sampleCount = 0;
        $orderSummary = array();
        foreach ($nphOrder as $order) {
            $samples = $order->getNphSamples()->toArray();
            foreach ($samples as $sample) {
                $sampleCount++;
                $moduleClass = 'App\Nph\Order\Modules\Module' . $order->getModule();
                $module = new $moduleClass($order->getVisitType());
                $sampleName = $module->getSampleLabelFromCode($sample->getSampleCode());
                $timePointsDisplay = $module->getTimePoints();
                $sampleCollectionVolume = $module->getSampleCollectionVolumeFromCode($sample->getSampleCode());
                $orderSummary[$order->getModule()]
                [$order->getVisitType()]
                [$order->getTimepoint()]
                [$module->getSampleType($sample->getSampleCode())]
                [$sample->getSampleCode()] = [
                    'sampleId' => $sample->getSampleID(),
                    'sampleName' => $sampleName,
                    'orderId' => $order->getOrderId(),
                    'healthProOrderId' => $order->getId(),
                    'createDate' => $order->getCreatedTs()->format('Y-M-D'),
                    'sampleStatus' => $sample->getStatus(),
                    'sampleType' => $module->getSampleType($sample->getSampleCode()),
                    'sampleCollectionVolume' => $sampleCollectionVolume,
                    'timepointDisplayName' => $timePointsDisplay[$order->getTimepoint()],
                    'sampleTypeDisplayName' => ucwords($module->getSampleType($sample->getSampleCode()))
                ];
            }
        }
        $returnArray = array();
        $returnArray['order'] = $orderSummary;
        $returnArray['sampleCount'] = $sampleCount;
        return $returnArray;
    }

    public function hasAtLeastOneAliquotSample(array $formData, string $sampleCode): bool
    {
        $aliquots = $this->getAliquots($sampleCode);
        foreach (array_keys($aliquots) as $aliquotCode) {
            if (isset($formData[$aliquotCode])) {
                foreach ($formData[$aliquotCode] as $aliquotId) {
                    if ($aliquotId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function saveOrderFinalization(array $formData, NphSample $sample): NphSample
    {
        $sampleCode = $sample->getSampleCode();
        $aliquots = $this->getAliquots($sampleCode);
        if (!empty($aliquots)) {
            foreach ($aliquots as $aliquotCode => $aliquot) {
                foreach ($formData[$aliquotCode] as $key => $aliquotId) {
                    if ($aliquotId) {
                        $nphAliquot = new NphAliquot();
                        $nphAliquot->setNphSample($sample);
                        $nphAliquot->setAliquotId($aliquotId);
                        $nphAliquot->setAliquotCode($aliquotCode);
                        $nphAliquot->setAliquotTs($formData["{$aliquotCode}AliquotTs"][$key]);
                        $nphAliquot->setVolume($formData["{$aliquotCode}Volume"][$key]);
                        $nphAliquot->setUnits($aliquot['units']);
                        $this->em->persist($nphAliquot);
                        $this->em->flush();
                        $this->loggerService->log(Log::NPH_ALIQUOT_CREATE, $nphAliquot->getId());
                    }
                }
            }
        }
        $sample->setCollectedTs($formData["{$sampleCode}CollectedTs"]);
        $sample->setFinalizedNotes($formData["{$sampleCode}Notes"]);
        $sample->setFinalizedUser($this->user);
        $sample->setFinalizedSite($this->site);
        $sample->setFinalizedTs(new DateTime());
        if ($sample->getNphOrder()->getOrderType() === 'urine') {
            $sample->setSampleMetadata($this->jsonEncodeMetadata($formData, ['urineColor',
                'urineClarity']));
        }
        $this->em->persist($sample);
        $this->em->flush();

        if ($sample->getNphOrder()->getOrderType() === 'stool') {
            $order = $sample->getNphOrder();
            $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType', 'bowelQuality']));
            $this->em->persist($order);
            $this->em->flush();
        }
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
        return $sample;
    }

    public function getExistingSampleData(NphSample $sample): array
    {
        $sampleData = [];
        $sampleCode = $sample->getSampleCode();
        $sampleData[$sampleCode . 'CollectedTs'] = $sample->getCollectedTs();
        $sampleData[$sampleCode . 'Notes'] = $sample->getFinalizedNotes();
        if ($sample->getNphOrder()->getOrderType() === 'urine') {
            if ($sample->getSampleMetaData()) {
                $sampleMetadata = json_decode($sample->getSampleMetaData(), true);
                if (!empty($sampleMetadata['urineColor'])) {
                    $sampleData['urineColor'] = $sampleMetadata['urineColor'];
                }
                if (!empty($sampleMetadata['urineClarity'])) {
                    $sampleData['urineClarity'] = $sampleMetadata['urineClarity'];
                }
            }
        }
        if ($sample->getNphOrder()->getOrderType() === 'stool') {
            $order = $sample->getNphOrder();
            if ($order->getMetadata()) {
                $orderMetadata = json_decode($order->getMetadata(), true);
                if (!empty($orderMetadata['bowelType'])) {
                    $sampleData['bowelType'] = $orderMetadata['bowelType'];
                }
                if (!empty($orderMetadata['bowelQuality'])) {
                    $sampleData['bowelQuality'] = $orderMetadata['bowelQuality'];
                }
            }
        }
        $aliquots = $sample->getNphAliquots();
        foreach ($aliquots as $aliquot) {
            $sampleData[$aliquot->getAliquotCode()][] = $aliquot->getAliquotId();
            $sampleData["{$aliquot->getAliquotCode()}AliquotTs"][] = $aliquot->getAliquotTs();
            $sampleData["{$aliquot->getAliquotCode()}Volume"][] = $aliquot->getVolume();
        }
        return $sampleData;
    }

    public function saveSamplesModification(array $formData, string $type, NphOrder $order): NphOrder
    {
        if ($formData['reason'] === 'OTHER') {
            $formData['reason'] = $formData['otherText'];
        }
        foreach ($order->getNphSamples() as $sample) {
            if (isset($formData[$sample->getSampleCode()]) && $formData[$sample->getSampleCode()] === true) {
                $this->saveSampleModificationsData($sample, $type, $formData['reason']);
            }
        }
        return $order;
    }

    private function saveSampleModificationsData(NphSample $sample, string $type, string $reason): void
    {
        $sample->setModifiedTs(new \DateTime());
        $sample->setModifiedSite($this->site);
        $sample->setModifiedUser($this->user);
        $sample->setModifyReason($reason);
        $sample->setModifyType($type);
        $this->em->persist($sample);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
    }
}
