<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\NphAdminOrderEditLog;
use App\Entity\NphAliquot;
use App\Entity\NphDlw;
use App\Entity\NphGenerateOrderWarningLog;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSampleProcessingStatus;
use App\Entity\NphSite;
use App\Entity\Order;
use App\Entity\User;
use App\Form\Nph\NphOrderForm;
use App\Helper\NphDietPeriodStatus;
use App\Helper\NphParticipant;
use App\Service\LoggerService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

class NphOrderService
{
    private $em;
    private $userService;
    private $siteService;
    private $loggerService;
    private $rdrApiService;

    private $module;
    private $visit;
    private $moduleObj;
    private $participantId;
    private $biobankId;
    private $user;
    private $site;

    private static $placeholderSamples = ['STOOL', 'STOOL2'];

    public function __construct(
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService,
        RdrApiService $rdrApiService
    ) {
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
        $this->rdrApiService = $rdrApiService;
        $this->user = $this->em->getRepository(User::class)->find($this->userService->getUser()->getId());
        $this->site = $this->siteService->getSiteId();
    }

    public function loadModules(string $module, string $visit, string $participantId, string $biobankId): void
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $this->moduleObj = new $moduleClass($visit);

        $this->module = $module;
        $this->visit = $visit;
        $this->participantId = $participantId;
        $this->biobankId = $biobankId;
    }

    public function getVisitDiet(): string
    {
        return $this->moduleObj->getVisitDiet($this->visit);
    }

    public function getTimePointSamples(): array
    {
        return $this->moduleObj->getTimePointSamples();
    }

    public function getTimePoints(): array
    {
        return $this->moduleObj->getTimePoints();
    }

    public function getRdrTimePoints(): array
    {
        return $this->moduleObj->getRdrTimePoints();
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

    public function getSampleIdentifierFromCode(string $sampleCode): string
    {
        return $this->moduleObj->getSampleIdentifierFromCode($sampleCode);
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
                'disabled' => $sampleObj->getFinalizedTs() || $sampleObj->getModifyType() === NphSample::CANCEL,
                'cancelled' => $sampleObj->getModifyType() === NphSample::CANCEL ? 1 : 0,
                'finalized' => $sampleObj->getFinalizedTs() ? 1 : 0
            ];
        }
        return $sampleLabels;
    }

    public function getExistingOrdersData(): array
    {
        $ordersData = [];
        $orders = $this->em->getRepository(NphOrder::class)->getOrdersByVisitType(
            $this->participantId,
            $this->visit,
            $this->module
        );
        $addStoolKit = $addStoolKit2 = true;
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                if ($sample->getModifyType() !== NphSample::CANCEL) {
                    if (in_array($sample->getSampleCode(), $this->getSamplesByType(NphOrder::TYPE_STOOL))) {
                        if ($addStoolKit) {
                            $ordersData['stoolKit'] = $order->getOrderId();
                            $ordersData[$order->getTimepoint()][] = NphSample::SAMPLE_STOOL;
                            $addStoolKit = false;
                        }
                        $ordersData[$sample->getSampleCode()] = $sample->getSampleId();
                    } elseif (in_array($sample->getSampleCode(), $this->getSamplesByType(NphOrder::TYPE_STOOL_2))) {
                        if ($addStoolKit2) {
                            $ordersData['stoolKit2'] = $order->getOrderId();
                            $ordersData[$order->getTimepoint()][] = NphSample::SAMPLE_STOOL_2;
                            $addStoolKit2 = false;
                        }
                        $ordersData[$sample->getSampleCode()] = $sample->getSampleId();
                    } else {
                        $ordersData[$order->getTimepoint()][] = $sample->getSampleCode();
                    }
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
            $this->visit,
            $this->module
        );
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                if ($sample->getModifyType() !== NphSample::CANCEL) {
                    $samplesData[$order->getTimepoint()][$sample->getSampleCode()] = [
                        'id' => $order->getId(),
                        'orderId' => $order->getOrderId(),
                        'sampleGroup' => $order->getSampleGroupBySampleCode($sample->getSampleCode()),
                    ];
                }
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

    public function generateSampleGroup(): string
    {
        $attempts = 0;
        $nphSampleRepository = $this->em->getRepository(NphSample::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($nphSampleRepository->findOneBy(['sampleGroup' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (empty($id)) {
            throw new \Exception('Failed to generate unique sample group');
        }
        return $id;
    }

    public function createOrdersAndSamples(array $formData): string
    {
        $sampleGroup = $this->generateSampleGroup();
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                $samplesByType = [];
                foreach ($samples as $sample) {
                    if (in_array($sample, $this->getSamplesByType(NphOrder::TYPE_NAIL))) {
                        $samplesByType['nail'][] = $sample;
                    } elseif (in_array($sample, $this->getSamplesByType(NphOrder::TYPE_BLOOD))) {
                        $samplesByType['blood'][] = $sample;
                    } elseif (!in_array($sample, self::$placeholderSamples)) {
                        $nphOrder = $this->createOrder($timePoint, $this->getSampleType($sample), null, $formData['downtime_generated'], $formData['createdTs']);
                        $this->createSample($sample, $nphOrder, $sampleGroup);
                    }
                }
                if (!empty($samplesByType['nail'])) {
                    $this->createOrderWithSamples($timePoint, NphOrder::TYPE_NAIL, $samplesByType['nail'], $sampleGroup, $formData['downtime_generated'], $formData['createdTs']);
                }
                if (!empty($samplesByType['blood'])) {
                    $this->createOrderWithSamples($timePoint, NphOrder::TYPE_BLOOD, $samplesByType['blood'], $sampleGroup, $formData['downtime_generated'], $formData['createdTs']);
                }
            }
        }
        // For stool kit samples
        if (!empty($formData['stoolKit'])) {
            $nphOrder = $this->createOrder($this->getStoolTimePoint($formData, NphSample::SAMPLE_STOOL), NphOrder::TYPE_STOOL, $formData['stoolKit'], $formData['downtime_generated'], $formData['createdTs']);
            foreach ($this->getSamplesByType(NphOrder::TYPE_STOOL) as $stoolSample) {
                if (!empty($formData[$stoolSample])) {
                    $this->createSample($stoolSample, $nphOrder, $sampleGroup, $formData[$stoolSample]);
                }
            }
        }
        if (!empty($formData['stoolKit2'])) {
            $nphOrder = $this->createOrder($this->getStoolTimePoint($formData, NphSample::SAMPLE_STOOL_2), NphOrder::TYPE_STOOL_2, $formData['stoolKit2'], $formData['downtime_generated'], $formData['createdTs']);
            foreach ($this->getSamplesByType(NphOrder::TYPE_STOOL_2) as $stoolSample) {
                if (!empty($formData[$stoolSample])) {
                    $this->createSample($stoolSample, $nphOrder, $sampleGroup, $formData[$stoolSample]);
                }
            }
        }
        return $sampleGroup;
    }

    public function createOrder(string $timePoint, string $orderType, string $orderId = null, bool $downtimeGenerated = false, ?DateTime $downtimeGeneratedTs = null): NphOrder
    {
        if ($orderId === null) {
            $orderId = $this->generateOrderId();
        }
        if (empty($downtimeGeneratedTs)) {
            $downtimeGeneratedTs = new DateTime();
        }
        $nphOrder = new NphOrder();
        $nphOrder->setModule($this->module);
        $nphOrder->setVisitPeriod($this->visit);
        $nphOrder->setTimepoint($timePoint);
        $nphOrder->setOrderId($orderId);
        $nphOrder->setParticipantId($this->participantId);
        $nphOrder->setBiobankId($this->biobankId);
        $nphOrder->setUser($this->user);
        $nphOrder->setSite($this->site);
        $nphOrder->setCreatedTs($downtimeGeneratedTs);
        $nphOrder->setCreatedTimezoneId($this->getTimezoneid());
        $nphOrder->setOrderType($orderType);
        $nphOrder->setDowntimeGenerated($downtimeGenerated);
        $nphOrder->setDowntimeGeneratedUser($downtimeGenerated ? $this->user : null);
        if ($downtimeGenerated) {
            $nphOrder->setDowntimeGeneratedTs(new DateTime());
        }
        $this->em->persist($nphOrder);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_ORDER_CREATE, $nphOrder->getId());
        return $nphOrder;
    }

    public function createSample(string $sample, NphOrder $nphOrder, string $sampleGroup, string $sampleId = null): NphSample
    {
        if ($sampleId === null) {
            $sampleId = $this->generateSampleId();
        }
        $nphSample = new NphSample();
        $nphSample->setSampleGroup($sampleGroup);
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId($sampleId);
        $nphSample->setSampleCode($sample);
        $this->em->persist($nphSample);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_SAMPLE_CREATE, $nphSample->getId());
        return $nphSample;
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

    public function saveOrderCollection(array $formData, NphOrder $order): ?NphOrder
    {
        try {
            $orderType = $order->getOrderType();
            $atLeastOneSampleIsFinalized = false;
            foreach ($order->getNphSamples() as $nphSample) {
                if ($atLeastOneSampleIsFinalized === false) {
                    $atLeastOneSampleIsFinalized = (bool) $nphSample->getRdrId();
                }
                $sampleCode = $nphSample->getSampleCode();
                if (isset($formData[$sampleCode])) {
                    if ($formData[$sampleCode]) {
                        $nphSample->setCollectedUser($this->user);
                        $nphSample->setCollectedSite($this->site);
                        $collectedTs = $orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2 ?
                            $formData[$orderType . 'CollectedTs'] : $formData[$sampleCode . 'CollectedTs'];
                        $nphSample->setCollectedTs($collectedTs);
                        $nphSample->setCollectedTimezoneId($this->getTimezoneid());
                        $nphSample->setCollectedNotes($formData[$sampleCode . 'Notes']);
                        if ($order->getOrderType() === NphOrder::TYPE_URINE || $order->getOrderType() === NphOrder::TYPE_24URINE) {
                            $nphSample->setSampleMetadata($this->jsonEncodeMetadata($formData, ['urineColor', 'urineClarity', 'totalCollectionVolume']));
                        }
                    } else {
                        $nphSample->setCollectedUser(null);
                        $nphSample->setCollectedSite(null);
                        $nphSample->setCollectedTs(null);
                        $nphSample->setCollectedTimezoneId(null);
                        $nphSample->setCollectedNotes(null);
                    }
                }
                $this->em->persist($nphSample);
                $this->em->flush();
                $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $nphSample->getId());
            }
            if (($orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2) && $atLeastOneSampleIsFinalized === false) {
                $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType',
                    'bowelQuality']));
                $this->em->persist($order);
                $this->em->flush();
                $this->loggerService->log(Log::NPH_ORDER_UPDATE, $order->getId());
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function saveAdminOrderEdits(array $formData, NphOrder $order): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $orderType = $order->getOrderType();
            $originalOrderGenerationTs = $order->getCreatedTs();
            $originalOrderGenerationTimezoneId = $order->getCreatedTimezoneId();
            if ($oldOrderEditLog = $this->em->getRepository(NphAdminOrderEditLog::class)->findOneBy(['orderId' => $order->getOrderId()])) {
                $originalOrderGenerationTs = $oldOrderEditLog->getOriginalOrderGenerationTs();
                $originalOrderGenerationTimezoneId = $oldOrderEditLog->getOriginalOrderGenerationTimezoneId();
            }

            // Update order generation time
            $order->setCreatedTs($formData[$orderType . 'GenerationTs']);
            $order->setCreatedTimezoneId($this->getTimezoneid());
            $this->em->persist($order);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_ORDER_UPDATE, $order->getId());

            // Update sample collection time
            foreach ($order->getNphSamples() as $nphSample) {
                $sampleCode = $nphSample->getSampleCode();
                if (isset($formData[$sampleCode])) {
                    if ($formData[$sampleCode]) {
                        $collectedTs = $orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2 ?
                            $formData[$orderType . 'CollectedTs'] : $formData[$sampleCode . 'CollectedTs'];
                        $nphSample->setCollectedTs($collectedTs);
                        $nphSample->setCollectedTimezoneId($this->getTimezoneid());
                        $nphSample->setCollectedNotes($formData[$sampleCode . 'Notes']);
                    } else {
                        $nphSample->setCollectedUser(null);
                        $nphSample->setCollectedSite(null);
                        $nphSample->setCollectedTs(null);
                        $nphSample->setCollectedTimezoneId(null);
                        $nphSample->setCollectedNotes(null);
                    }
                }
                $this->em->persist($nphSample);
                $this->em->flush();
                $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $nphSample->getId());
                if ($nphSample->getRdrId() && $nphSample->getModifyType() !== NphSample::CANCEL) {
                    $this->sendToRdr($nphSample, NphSample::UNLOCK, false, true);
                }
            }

            // Log entry
            $orderEditLog = new NphAdminOrderEditLog();
            $orderEditLog->setUser($this->user);
            $orderEditLog->setOrderId($order->getOrderId());
            $orderEditLog->setOriginalOrderGenerationTs($originalOrderGenerationTs);
            $orderEditLog->setOriginalOrderGenerationTimezoneId($originalOrderGenerationTimezoneId);
            $orderEditLog->setUpdatedOrderGenerationTs($formData[$orderType . 'GenerationTs']);
            $orderEditLog->setUpdatedOrderGenerationTimezoneId($this->getTimezoneid());
            $orderEditLog->setCreatedTs(new DateTime());
            $orderEditLog->setCreatedTimezoneId($this->getTimezoneid());
            $this->em->persist($orderEditLog);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_ADMIN_ORDER_EDIT_LOG_CREATE, $orderEditLog->getId());
            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollBack();
            return false;
        }
    }

    public function getExistingOrderCollectionData(NphOrder $order, bool $setOrderGenerationTs = false): array
    {
        $orderCollectionData = [];
        $orderType = $order->getOrderType();
        if ($setOrderGenerationTs) {
            $orderCollectionData[$orderType . 'GenerationTs'] = $order->getCreatedTs();
        }
        if ($orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2) {
            $orderCollectionData[$orderType . 'CollectedTs'] = $order->getCollectedTs();
        }
        foreach ($order->getNphSamples() as $nphSample) {
            $sampleCode = $nphSample->getSampleCode();
            if ($orderType !== NphOrder::TYPE_STOOL && $orderType !== NphOrder::TYPE_STOOL_2) {
                $orderCollectionData[$sampleCode . 'CollectedTs'] = $nphSample->getCollectedTs();
            }
            if ($nphSample->getCollectedTs()) {
                $orderCollectionData[$sampleCode] = true;
            }
            $orderCollectionData[$sampleCode . 'Notes'] = $nphSample->getCollectedNotes();
            if ($order->getOrderType() === NphOrder::TYPE_URINE || $order->getOrderType() === NphOrder::TYPE_24URINE) {
                if ($nphSample->getSampleMetaData()) {
                    $sampleMetadata = json_decode($nphSample->getSampleMetaData(), true);
                    if (!empty($sampleMetadata['urineColor'])) {
                        $orderCollectionData['urineColor'] = $sampleMetadata['urineColor'];
                    }
                    if (!empty($sampleMetadata['urineClarity'])) {
                        $orderCollectionData['urineClarity'] = $sampleMetadata['urineClarity'];
                    }
                    if (!empty($sampleMetadata['totalCollectionVolume'])) {
                        $orderCollectionData['totalCollectionVolume'] = $sampleMetadata['totalCollectionVolume'];
                    }
                }
            }
        }
        if ($order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2) {
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
                if ($metaDataType === 'freezedTs') {
                    $metadata[$metaDataType] = $formData[$metaDataType]->getTimestamp();
                } else {
                    $metadata[$metaDataType] = $formData[$metaDataType];
                }
            }
        }
        return !empty($metadata) ? json_encode($metadata) : null;
    }

    public function getSamplesMetadata(NphOrder $order): array
    {
        $metadata = [];
        if ($order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2) {
            $metadata = json_decode($order->getMetadata(), true);
            $metadata['bowelType'] = $this->mapMetadata($metadata, 'bowelType', NphOrderForm::$bowelMovements);
            $metadata['bowelQuality'] = $this->mapMetadata($metadata, 'bowelQuality', NphOrderForm::$bowelMovementQuality);
            $metadata['freezedTs'] = $metadata['freezedTs'] ?? null;
        } elseif ($order->getOrderType() === NPHOrder::TYPE_URINE || $order->getOrderType() === NPHOrder::TYPE_24URINE) {
            $metadata = json_decode($order->getNphSamples()[0]->getSampleMetadata(), true);
            $metadata['urineColor'] = $this->mapMetadata($metadata, 'urineColor', NphOrderForm::$urineColors);
            $metadata['urineClarity'] = $this->mapMetadata($metadata, 'urineClarity', NphOrderForm::$urineClarity);
            if ($order->getOrderType() === NPHOrder::TYPE_24URINE) {
                $metadata['totalCollectionVolume'] = $metadata['totalCollectionVolume'] ?? null;
            }
        }
        return $metadata;
    }

    public function getParticipantOrderSummary(string $participantid): array
    {
        $OrderRepository = $this->em->getRepository(NphOrder::class);
        $orderInfo = $OrderRepository->getOrdersByParticipantId($participantid);
        return $this->generateOrderSummaryArray($orderInfo);
    }

    public function getParticipantOrderSummaryByModuleAndVisit(string $participantid, string $module, string $visit): array
    {
        $OrderRepository = $this->em->getRepository(NphOrder::class);
        $orderInfo = $OrderRepository->findBy(['participantId' => $participantid, 'visitPeriod' => $visit, 'module' =>
            $module]);
        $orderSummary = $this->generateOrderSummaryArray($orderInfo);
        $orderSummary['order'] = $orderSummary['order'][$module][$visit];
        return $orderSummary;
    }

    //TODO: Update these summary methods to return some sort of data object instead of arrays.
    public function getParticipantOrderSummaryByModuleVisitAndSampleGroup(
        string $participantid,
        string $module,
        string $visit,
        string $sampleGroup
    ): array {
        $orderInfo = $this->em->getRepository(NphOrder::class)->getOrdersBySampleGroup($participantid, $sampleGroup);
        $orderSummary = $this->generateOrderSummaryArray($orderInfo);
        $orderSummary['order'] = $orderSummary['order'][$module][$visit];
        return $orderSummary;
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

    public function saveFinalization(array $formData, NphSample $sample, $biobankFinalization = false): bool
    {
        $order = $sample->getNphOrder();
        if ($order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2) {
            return $this->saveStoolSampleFinalization($formData, $sample, $biobankFinalization);
        }
        return $this->saveSampleFinalization($formData, $sample, $biobankFinalization);
    }

    public function saveReFinalization(?string $notes, NphSample $sample): bool
    {
        return $this->saveSampleReFinalization($notes, $sample);
    }

    public function getExistingSampleData(NphSample $sample): array
    {
        $sampleData = [];
        $sampleCode = $sample->getSampleCode();
        $order = $sample->getNphOrder();
        $sampleData[$sampleCode . 'CollectedTs'] = $sample->getCollectedTs();
        $sampleData[$sampleCode . 'Notes'] = $sample->getFinalizedNotes();
        $sampleData[$sampleCode . 'CollectedNotes'] = $sample->getCollectedNotes();
        if ($order->getOrderType() === NphOrder::TYPE_URINE || $order->getOrderType() === NphOrder::TYPE_24URINE) {
            if ($sample->getSampleMetaData()) {
                $sampleMetadata = json_decode($sample->getSampleMetaData(), true);
                if (!empty($sampleMetadata['urineColor'])) {
                    $sampleData['urineColor'] = $sampleMetadata['urineColor'];
                }
                if (!empty($sampleMetadata['urineClarity'])) {
                    $sampleData['urineClarity'] = $sampleMetadata['urineClarity'];
                }
                if (!empty($sampleMetadata['totalCollectionVolume'])) {
                    $sampleData['totalCollectionVolume'] = $sampleMetadata['totalCollectionVolume'];
                }
            }
        }
        if ($order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2) {
            $sampleData[$sampleCode . 'CollectedTs'] = $order->getCollectedTs();
            if ($order->getMetadata()) {
                $orderMetadata = json_decode($order->getMetadata(), true);
                if (!empty($orderMetadata['bowelType'])) {
                    $sampleData['bowelType'] = $orderMetadata['bowelType'];
                }
                if (!empty($orderMetadata['bowelQuality'])) {
                    $sampleData['bowelQuality'] = $orderMetadata['bowelQuality'];
                }
                if (!empty($orderMetadata['freezedTs'])) {
                    $freezedTs = new \DateTime();
                    $freezedTs->setTimestamp($orderMetadata['freezedTs']);
                    $sampleData['freezedTs'] = $freezedTs;
                }
            }
        }
        if ($order->getOrderType() === NphOrder::TYPE_MODULE_3_SALIVA) {
            foreach ($order->getNphSamples() as $nphSample) {
                foreach ($nphSample->getNphAliquots() as $aliquot) {
                    if (!empty($aliquot->getAliquotMetadata())) {
                        foreach ($aliquot->getAliquotMetadata() as $metadataKey => $metadataValue) {
                            $sampleData[$metadataKey][] = $metadataValue;
                        }
                    }
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

    public function getSamplesWithStatus(): array
    {
        $samplesData = [];
        $orders = $this->em->getRepository(NphOrder::class)->getOrdersByVisitType(
            $this->participantId,
            $this->visit,
            $this->module
        );
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                $samplesData[$order->getTimepoint()][$sample->getSampleCode()] = $sample->getStatus();
            }
        }
        return $samplesData;
    }

    public function saveSamplesModification(array $formData, string $type, NphOrder $order): bool
    {
        $status = true;
        foreach ($order->getNphSamples() as $sample) {
            if (isset($formData[$sample->getSampleCode()]) && $formData[$sample->getSampleCode()] === true) {
                $sampleObject = $this->getCancelRestoreRdrObject($type, $formData['reason']);
                if ($sample->getRdrId()) {
                    if ($this->cancelRestoreSample(
                        $sample->getRdrId(),
                        $order->getParticipantId(),
                        $type,
                        $sampleObject
                    )) {
                        $this->saveSampleModificationsData($sample, $type, $formData);
                    } else {
                        $status = false;
                    }
                } else {
                    $this->saveSampleModificationsData($sample, $type, $formData);
                }
            }
        }
        return $status;
    }

    public function updateSampleModificationBulk(array $formData, string $type): bool
    {
        $status = true;
        foreach ($formData as $sampleId => $checked) {
            if ($checked === true) {
                $sample = $this->em->getRepository(NphSample::class)->findOneBy(['sampleId' => $sampleId]);
                if ($sample !== null) {
                    $sampleObject = $this->getCancelRestoreRdrObject($type, $formData['reason']);
                    if ($sample->getRdrId()) {
                        if ($this->cancelRestoreSample(
                            $sample->getRdrId(),
                            $sample->getNphOrder()->getParticipantId(),
                            $type,
                            $sampleObject
                        )) {
                            $this->saveSampleModificationsData($sample, $type, $formData);
                        } else {
                            $status = false;
                        }
                    } else {
                        $this->saveSampleModificationsData($sample, $type, $formData);
                    }
                }
            }
        }
        return $status;
    }

    public function saveSampleModification(array $formData, string $type, NphSample $sample): NphSample
    {
        $this->saveSampleModificationsData($sample, $type, $formData);
        return $sample;
    }

    public function checkDuplicateAliquotId(array $formData, string $sampleCode, array $existingAliquotIds = []): array
    {
        $aliquots = $this->getAliquots($sampleCode);
        foreach (array_keys($aliquots) as $aliquotCode) {
            if (isset($formData[$aliquotCode])) {
                foreach ($formData[$aliquotCode] as $key => $aliquotId) {
                    if (!in_array($aliquotId, $existingAliquotIds) && $this->em->getRepository(NphAliquot::class)->findOneBy(['aliquotId' => $aliquotId])) {
                        return [
                            'key' => $key,
                            'aliquotCode' => $aliquotCode
                        ];
                    }
                }
            }
        }
        return [];
    }

    public function hasDuplicateAliquotsInForm(array $formData, string $sampleCode): bool
    {
        $aliquots = $this->getAliquots($sampleCode);
        $totalAliquotCodes = [];
        foreach (array_keys($aliquots) as $aliquotCode) {
            if (isset($formData[$aliquotCode])) {
                $totalAliquotCodes = array_merge($totalAliquotCodes, $formData[$aliquotCode]);
            }
        }
        return $this->hasDuplicateIds($totalAliquotCodes);
    }

    public function getRdrObject(NphOrder $order, NphSample $sample, string $type = 'create', bool $biobankUser = false): \stdClass
    {
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $order->getParticipantId();
        $nphSite = $this->em->getRepository(NphSite::class)->findOneBy(['googleGroup' => $sample->getFinalizedSite()]);
        $clientId = !empty($nphSite) ? $nphSite->getMayolinkAccount() : null;
        $identifiers = [
            [
                'system' => 'https://www.pmi-ops.org/order-id',
                'value' => $order->getOrderId()
            ],
            [
                'system' => 'https://www.pmi-ops.org/sample-id',
                'value' => $sample->getSampleId()
            ],
            [
                'system' => 'https://www.pmi-ops.org/client-id',
                'value' => $clientId
            ],
        ];
        $createdSite = NphSite::getSiteIdWithPrefix($order->getSite());
        $collectedSite = NphSite::getSiteIdWithPrefix($sample->getCollectedSite() ?? $sample->getFinalizedSite());
        $finalizedSite = NphSite::getSiteIdWithPrefix($sample->getFinalizedSite());
        $obj->createdInfo = $this->getUserSiteData($order->getUser()->getEmail(), $createdSite);
        $obj->collectedInfo = $this->getUserSiteData($sample->getCollectedUser() ? $sample->getCollectedUser()
            ->getEmail() : $sample->getFinalizedUser()->getEmail(), $collectedSite);
        $obj->finalizedInfo = $this->getUserSiteData($sample->getFinalizedUser()->getEmail(), $finalizedSite);
        $obj->identifier = $identifiers;
        $createdTs = $order->getCreatedTs();
        $createdTs->setTimezone(new \DateTimeZone('UTC'));
        $obj->created = $createdTs->format('Y-m-d\TH:i:s\Z');
        $obj->module = $order->getModule();
        $obj->visitPeriod = $this->getVisitTypes()[$order->getVisitPeriod()];
        // Handle RDR specific timepoint needs
        if ($this->getRdrTimePoints() && isset($this->getRdrTimePoints()[$order->getTimepoint()])) {
            $rdrTimePoint = $this->getRdrTimePoints()[$order->getTimepoint()];
        } else {
            $rdrTimePoint = $this->getTimePoints()[$order->getTimepoint()];
        }
        $obj->timepoint = $rdrTimePoint;
        $sampleInfo = $this->getSamples();
        $sampleIdentifier = $this->getSampleIdentifierFromCode($sample->getSampleCode());
        $sampleDescription = $sampleInfo[$sample->getSampleCode()];
        $samplesMetadata = $this->getSamplesMetadata($order);
        $obj->sample = $sample->getRdrSampleObj($sampleIdentifier, $sampleDescription, $samplesMetadata);
        $aliquotsInfo = $this->getAliquots($sample->getSampleCode());
        if ($aliquotsInfo) {
            $obj->aliquots = $sample->getRdrAliquotsSampleObj($aliquotsInfo);
        }
        $notes = [
            'collected' => $sample->getCollectedNotes(),
            'finalized' => $sample->getFinalizedNotes()
        ];
        $obj->notes = $notes;
        if ($type === 'amend') {
            if ($biobankUser) {
                $obj->amendedReason = NphSample::BIOBANK_MODIFY_REASON;
                $obj->amendedInfo = $this->getUserSiteData(
                    $this->user->getEmail(),
                    NphSite::getSiteIdWithPrefix($sample->getFinalizedSite())
                );
            } else {
                $obj->amendedReason = $sample->getModifyReason();
                $obj->amendedInfo = $this->getUserSiteData(
                    $sample->getModifiedUser() ? $sample->getModifiedUser()->getEmail() : $this->user->getEmail(),
                    NphSite::getSiteIdWithPrefix($sample->getModifiedSite() ?? $this->site)
                );
            }
        }
        return $obj;
    }

    public function getCancelRestoreRdrObject(string $type, string $reason): \stdClass
    {
        $obj = new \StdClass();
        $statusType = $type === NphSample::CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->amendedReason = $reason;
        $user = $this->user->getEmail();
        $site = NphSite::getSiteIdWithPrefix($this->site);
        $obj->{$statusType . 'Info'} = $this->getUserSiteData($user, $site);
        return $obj;
    }

    public function cancelRestoreSample(
        string $orderId,
        string $participantId,
        string $type,
        \stdClass $sampleObject
    ): bool {
        try {
            $response = $this->rdrApiService->patch(
                "rdr/v1/api/v1/nph/Participant/{$participantId}/BiobankOrder/{$orderId}",
                $sampleObject
            );
            $result = json_decode($response->getBody()->getContents());
            $rdrStatus = $type === NphSample::CANCEL ? 'cancelled' : 'restored';
            if (is_object($result) && isset($result->status) && $result->status === $rdrStatus) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getVisitTypes(): array
    {
        return $this->moduleObj->getVisitTypes();
    }

    public function validateGenerateOrdersData(array $formData): array
    {
        $formErrors = [];
        $hasSample = false;
        foreach (array_keys($this->getTimePoints()) as $timePoint) {
            if (!empty($formData[$timePoint])) {
                $hasSample = true;
                break;
            }
        }
        if (empty($formData['stoolKit']) && empty($formData['stoolKit2'])) {
            if ($hasSample === false) {
                $formErrors[] = [
                    'field' => 'checkAll',
                    'message' => 'Please select or enter at least one sample'
                ];
                return $formErrors;
            }
        } else {
            $stoolKitField = 'stoolKit';
            $stoolType = NphOrder::TYPE_STOOL;
            if (!empty($formData['stoolKit2'])) {
                $stoolKitField = 'stoolKit2';
                $stoolType = NphOrder::TYPE_STOOL_2;
            }
            $nphOrder = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $formData[$stoolKitField]
            ]);
            if ($nphOrder) {
                $formErrors[] = [
                    'field' => $stoolKitField,
                    'message' => 'This Kit ID has already been used for another order'
                ];
            }
            $totalStoolTubes = [];
            foreach ($this->getSamplesByType($stoolType) as $stoolSample) {
                if (!empty($formData[$stoolSample])) {
                    $totalStoolTubes[] = $formData[$stoolSample];
                    $nphSample = $this->em->getRepository(NphSample::class)->findOneBy([
                        'sampleId' => $formData[$stoolSample]
                    ]);
                    if ($nphSample) {
                        $formErrors[] = [
                            'field' => $stoolSample,
                            'message' => 'This Tube ID has already been used for another sample'
                        ];
                    }
                }
            }
            if ($this->hasDuplicateIds($totalStoolTubes)) {
                $formErrors[] = [
                    'field' => 'checkAll',
                    'message' => 'Please enter unique Stool Tube IDs'
                ];
            }
        }
        return $formErrors;
    }

    public function isDietStarted(array $moduleDietStatus): bool
    {
        $visitDiet = $this->getVisitDiet();
        if (!isset($moduleDietStatus[$visitDiet])) {
            return false;
        }
        return $moduleDietStatus[$visitDiet] === NphParticipant::DIET_STARTED;
    }

    public function isDietStartedOrCompleted(array $moduleDietStatus): bool
    {
        $visitDiet = $this->getVisitDiet();
        if (!isset($moduleDietStatus[$visitDiet])) {
            return false;
        }
        return in_array($moduleDietStatus[$visitDiet], [NphParticipant::DIET_STARTED, NphParticipant::DIET_COMPLETED]);
    }

    public function saveDlwCollection(NphDlw $formData, $participantId, $module, $visit, bool $updateModifiedTs = true): NphDlw
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $formData->setNphParticipant($participantId);
            $formData->setModule($module);
            $formData->setVisitPeriod($visit);
            if ($updateModifiedTs) {
                $formData->setModifiedTimezoneId($this->getTimezoneid());
                $formData->setModifiedTs(new DateTime());
            }
            $formData->setUser($this->user);
            $this->em->persist($formData);
            $this->em->flush();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \Exception('Unable to save DLW collection');
        }
        try {
            $this->sendDLWToRdr($formData);
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \Exception('Unable to save DLW collection in RDR Please try Again');
        }
        $connection->commit();
        return $formData;
    }

    public function generateDlwSummary(array $dlwRepository): array
    {
        $dlwSummary = [];
        foreach ($dlwRepository as $dlw) {
            $dlwSummary[$dlw->getModule()][$dlw->getVisitPeriod()] = $dlw->getModifiedTs();
        }
        return $dlwSummary;
    }

    public function getDowntimeOrderSummary(): array
    {
        $orders = $this->getDowntimeGeneratedOrdersByModuleAndVisit($this->participantId, $this->module, $this->visit);
        $existingSamples = $this->getExistingOrdersData();
        $downtimeGenerated = [];
        $downtimeGenerated['orderInfo'] = [];
        $downtimeGenerated['sampleInfo'] = [];
        $orderNumber = 0;
        $seenSampleGroups = [];
        /** @var NphOrder $order */
        foreach ($orders as $order) {
            if (array_key_exists($order->getTimepoint(), $existingSamples)) {
                /** @var NphSample $sample */
                foreach ($order->getNphSamples() as $sample) {
                    if (!in_array($sample->getSampleGroup(), $seenSampleGroups, true)) {
                        $seenSampleGroups[] = $sample->getSampleGroup();
                        $orderNumber++;
                        $downtimeGenerated['orderInfo'][$orderNumber]['orderUser'] = $order->getUser()->getEmail();
                        $downtimeGenerated['orderInfo'][$orderNumber]['orderDowntimeCreatedTime'] = $order->getDowntimeGeneratedTs();
                        $downtimeGenerated['orderInfo'][$orderNumber]['orderCreatedTime'] = $order->getCreatedTs();
                    }
                    if (in_array($sample->getSampleCode(), $existingSamples[$order->getTimepoint()], true)) {
                        $downtimeGenerated['sampleInfo'][$order->getTimepoint()][$sample->getSampleCode()] = $orderNumber;
                    }
                }
            }
        }
        return $downtimeGenerated;
    }

    public function getSampleStatusCounts(array $nphOrderInfo): array
    {
        $moduleStatusCount = [];
        foreach (array_keys($nphOrderInfo) as $module) {
            if (count($nphOrderInfo[$module]['sampleStatusCount']) === 0) {
                $moduleStatusCount[$module] = ['active' => 0];
            }
            foreach ($nphOrderInfo[$module]['sampleStatusCount'] as $statusCount) {
                foreach ($statusCount as $status => $count) {
                    $moduleStatusCount[$module][$status] = isset($moduleStatusCount[$module][$status]) ? $moduleStatusCount[$module][$status] + $count : $count;
                    if ($status !== 'Canceled') {
                        $moduleStatusCount[$module]['active'] = isset($moduleStatusCount[$module]['active']) ? $moduleStatusCount[$module]['active'] + $count : $count;
                    }
                }
            }
        }
        return $moduleStatusCount;
    }

    public function saveSampleProcessingStatus(string $participantId, string $biobankId, array $formData, array $sampleStatusCounts): void
    {
        $nphSampleProcessingStatus = $this->em->getRepository(NphSampleProcessingStatus::class)->getSampleProcessingStatus($participantId, $formData['module'], $formData['period']);
        if (!$nphSampleProcessingStatus) {
            $nphSampleProcessingStatus = new NphSampleProcessingStatus();
            $nphSampleProcessingStatus->setParticipantId($participantId);
            $nphSampleProcessingStatus->setBiobankId($biobankId);
            $nphSampleProcessingStatus->setModule($formData['module']);
            $nphSampleProcessingStatus->setPeriod($formData['period']);
        } else {
            $nphSampleProcessingStatus->setPreviousStatus($nphSampleProcessingStatus->getStatus());
        }
        $nphSampleProcessingStatus->setUser($this->user);
        $nphSampleProcessingStatus->setSite($this->site);
        $nphSampleProcessingStatus->setStatus($formData['status']);
        $nphSampleProcessingStatus->setModifyType($formData['modifyType']);
        $nphSampleProcessingStatus->setModifiedTs(new \DateTime());
        $nphSampleProcessingStatus->setModifiedTimezoneId($this->getTimezoneid());
        $finalizedCount = isset($sampleStatusCounts[$formData['module']]['Finalized']) ? $sampleStatusCounts[$formData['module']]['Finalized'] : 0;
        $activeCount = isset($sampleStatusCounts[$formData['module']]['active']) ? $sampleStatusCounts[$formData['module']]['active'] : 0;
        $nphSampleProcessingStatus->setIncompleteSamples(max(0, $activeCount - $finalizedCount));
        $this->em->persist($nphSampleProcessingStatus);
        $this->em->flush();
    }

    public function getModuleDietPeriodsStatus(string $participantId, string $participantModule): array
    {
        $moduleDietPeriodsStatus = [
            1 => ['LMT' => 'not_started', 'LMTStool' => 'not_started'],
            2 => ['Period1' => 'not_started',
                'Period2' => 'not_started',
                'Period3' => 'not_started'],
            3 => ['Period1' => 'not_started',
                'Period2' => 'not_started',
                'Period3' => 'not_started'],
        ];
        $orderSamplesByModule = $this->em->getRepository(NphOrder::class)->getOrderSamplesByModule($participantId);
        foreach ($moduleDietPeriodsStatus as $module => $dietPeriodsStatus) {
            foreach (array_keys($dietPeriodsStatus) as $dietPeriod) {
                foreach ($orderSamplesByModule as $orderSample) {
                    if ($module == $orderSample['module'] && $dietPeriod === substr(
                        $orderSample['visitPeriod'],
                        0,
                        7
                    )) {
                        if ($orderSample['finalizedTs'] === null && $orderSample['modifyType'] !== 'cancel') {
                            $moduleDietPeriodsStatus[$module][$dietPeriod] = NphDietPeriodStatus::IN_PROGRESS_UNFINALIZED;
                            break;
                        }
                        if ($orderSample['finalizedTs'] !== null || $orderSample['modifyType'] === 'cancel') {
                            $moduleDietPeriodsStatus[$module][$dietPeriod] = NphDietPeriodStatus::IN_PROGRESS_FINALIZED;
                        }
                    }
                }
            }
        }
        foreach ($moduleDietPeriodsStatus as $module => $moduleDietPeriods) {
            foreach ($moduleDietPeriods as $period => $moduleDietStatus) {
                $dietCompleteStatus = $this->em->getRepository(NphSampleProcessingStatus::class)->findOneBy([
                    'participantId' => $participantId,
                    'module' => $module,
                    'period' => $period,
                    'status' => 1
                ], ['modifiedTs' => 'DESC']);
                if ($dietCompleteStatus) {
                    if ($moduleDietStatus === NphDietPeriodStatus::IN_PROGRESS_UNFINALIZED) {
                        $moduleDietPeriodsStatus[$module][$period] = 'error_' . $moduleDietStatus . '_complete';
                    } else {
                        $moduleDietPeriodsStatus[$module][$period] = $moduleDietStatus . '_complete';
                    }
                }
            }
        }

        if ($participantModule > 1) {
            for ($i = 1; $i <= 2; $i++) {
                $currentPeriod = 'Period' . $i;
                $nextPeriod = 'Period' . ($i + 1);
                if (!str_contains($moduleDietPeriodsStatus[$participantModule][$currentPeriod], 'complete') &&
                    $moduleDietPeriodsStatus[$participantModule][$nextPeriod] !== NphDietPeriodStatus::NOT_STARTED) {
                    if ($moduleDietPeriodsStatus[$participantModule][$currentPeriod] === NphDietPeriodStatus::IN_PROGRESS_FINALIZED) {
                        $moduleDietPeriodsStatus[$participantModule][$currentPeriod] = NphDietPeriodStatus::ERROR_NEXT_DIET_STARTED_FINALIZED;
                    } else {
                        $moduleDietPeriodsStatus[$participantModule][$currentPeriod] = NphDietPeriodStatus::ERROR_NEXT_DIET_STARTED;
                    }
                }
            }

            if ($moduleDietPeriodsStatus[$participantModule]['Period1'] !== NphDietPeriodStatus::NOT_STARTED &&
                !str_contains($moduleDietPeriodsStatus[1]['LMT'], 'complete')) {
                $moduleDietPeriodsStatus[1]['LMT'] = NphDietPeriodStatus::ERROR_NEXT_MODULE_STARTED;
            }
        }

        return $moduleDietPeriodsStatus;
    }

    public function getActiveDietPeriod(array $moduleDietPeriodStatus, string $currentModule): string
    {
        $completedCount = 0;
        foreach ($moduleDietPeriodStatus[$currentModule] as $dietPeriod => $status) {
            if (!in_array($status, [NphDietPeriodStatus::ERROR_IN_PROGRESS_UNFINALIZED_COMPLETE, NphDietPeriodStatus::IN_PROGRESS_FINALIZED_COMPLETE]) &&
                in_array($status, [NphDietPeriodStatus::IN_PROGRESS_UNFINALIZED, NphDietPeriodStatus::IN_PROGRESS_FINALIZED, NphDietPeriodStatus::NOT_STARTED])) {
                return $dietPeriod;
            }
            if (str_contains($status, 'complete')) {
                $completedCount++;
            }
        }
        if ($completedCount === 3) {
            return NphDietPeriodStatus::PERIOD3;
        }
        return NphDietPeriodStatus::PERIOD1;
    }

    public function getActiveModule(array $moduleDietPeriodStatus, string $currentModule): string
    {
        $module1DietStatus = $moduleDietPeriodStatus[1]['LMT'];
        if (in_array($module1DietStatus, [NphDietPeriodStatus::NOT_STARTED,
            NphDietPeriodStatus::IN_PROGRESS_UNFINALIZED, NphDietPeriodStatus::IN_PROGRESS_FINALIZED])) {
            return '1';
        }
        return $currentModule;
    }

    public function canGenerateOrders(string $participantId, string $module, string $dietPeriod, string $participantModule): bool
    {
        $moduleDietPeriodStatus = $this->getModuleDietPeriodsStatus($participantId, $participantModule);

        // Check if the previous diet period is Period1 or the diet is a module 1 diet
        $isPreviousDietPeriodStarted = ($module === '1' || $dietPeriod === 'Period1');

        if (!$isPreviousDietPeriodStarted) {
            $previousDietPeriodStatus = $moduleDietPeriodStatus[$module][($dietPeriod == 'Period2') ? 'Period1' : 'Period2'];
            $isPreviousDietPeriodStarted = ($previousDietPeriodStatus != NphDietPeriodStatus::NOT_STARTED);
        }
        $isSampleProcessingComplete = $this->em->getRepository(NphSampleProcessingStatus::class)->isSampleProcessingComplete($participantId, $module, $dietPeriod);
        return $isPreviousDietPeriodStarted && !$isSampleProcessingComplete;
    }

    public function saveGenerateOrderWarningLog(string $participantId, string $biobankId, array $formData, array $sampleStatusCounts): void
    {
        $nphGenerateOrderWarningLog = $this->em->getRepository(NphGenerateOrderWarningLog::class)->getGenerateOrderWarningLog($participantId, $formData['module'], $formData['period']);
        if (!$nphGenerateOrderWarningLog) {
            $nphGenerateOrderWarningLog = new NphGenerateOrderWarningLog();
            $nphGenerateOrderWarningLog->setParticipantId($participantId);
            $nphGenerateOrderWarningLog->setBiobankId($biobankId);
            $nphGenerateOrderWarningLog->setModule($formData['module']);
            $nphGenerateOrderWarningLog->setPeriod($formData['period']);
        }
        $nphGenerateOrderWarningLog->setUser($this->user);
        $nphGenerateOrderWarningLog->setSite($this->site);
        $nphGenerateOrderWarningLog->setModifiedTs(new \DateTime());
        $finalizedCount = $sampleStatusCounts[$formData['module']]['Finalized'] ?? 0;
        $activeCount = $sampleStatusCounts[$formData['module']]['active'] ?? 0;
        $nphGenerateOrderWarningLog->setIncompleteSamples(max(0, $activeCount - $finalizedCount));
        $nphGenerateOrderWarningLog->setModifiedTimezoneId($this->getTimezoneid());
        $this->em->persist($nphGenerateOrderWarningLog);
        $this->em->flush();
    }

    public function getModuleVisitTimePointSamples(string $module, string $participantId, string $biobankId): array
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $visits = $moduleClass::getVisitTypes();
        $visitTimePointSamples = [];
        foreach (array_keys($visits) as $visitKey) {
            $this->loadModules($module, $visitKey, $participantId, $biobankId);
            $visitTimePointSamples[$visitKey] = $this->getTimePointSamples();
        }
        return $visitTimePointSamples;
    }

    public function getModuleTimePoints(string $module, string $participantId, string $biobankId): array
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $visits = $moduleClass::getVisitTypes();
        $timePoints = [];
        foreach (array_keys($visits) as $visitKey) {
            $this->loadModules($module, $visitKey, $participantId, $biobankId);
            $timePoints[] = $this->getTimePoints();
        }
        $uniqueTimePoints = [];
        foreach ($timePoints as $timePoint) {
            foreach ($timePoint as $key => $value) {
                $uniqueTimePoints[$key] = $value;
            }
        }
        return $uniqueTimePoints;
    }


    private function generateOrderSummaryArray(array $nphOrder): array
    {
        $sampleCount = 0;
        $orderSummary = [];
        $statusCount = [];
        foreach ($nphOrder as $order) {
            $samples = $order->getNphSamples()->toArray();
            foreach ($samples as $sample) {
                $sampleCount++;
                $moduleClass = 'App\Nph\Order\Modules\Module' . $order->getModule();
                $module = new $moduleClass($order->getVisitPeriod());
                $sampleName = $module->getSampleLabelFromCode($sample->getSampleCode());
                $timePointsDisplay = $module->getTimePoints();
                $sampleCollectionVolume = $module->getSampleCollectionVolumeFromCode($sample->getSampleCode());
                $sampleStatus = $sample->getStatus();
                $orderId = $order->getOrderId();
                $orderSummary[$order->getModule()]
                [$order->getVisitPeriod()]
                [$order->getTimepoint()]
                [$module->getSampleType($sample->getSampleCode())]
                [$sample->getSampleCode()]
                [$order->getOrderId()] = [
                    'sampleId' => $sample->getSampleID(),
                    'sampleName' => $sampleName,
                    'orderId' => $order->getOrderId(),
                    'healthProOrderId' => $order->getId(),
                    'createDate' => $order->getCreatedTs()->format('m/d/Y'),
                    'sampleStatus' => $sampleStatus,
                    'sampleCollectionVolume' => $sampleCollectionVolume,
                    'timepointDisplayName' => $timePointsDisplay[$order->getTimepoint()],
                    'sampleTypeDisplayName' => $module->getSampleTypeDisplayName($sample->getSampleCode()),
                    'identifier' => $module->getSampleIdentifierFromCode($sample->getSampleCode()),
                    'visitDisplayName' => NphOrder::VISIT_DISPLAY_NAME_MAPPER[$order->getVisitPeriod()],
                    'sampleGroup' => $sample->getSampleGroup(),
                    'modifyType' => $sample->getModifyType(),
                    'orderStatus' => $order->getStatus(),
                    'oldVisitType' => $order->getVisitType(),
                    'orderSite' => $order->getSite(),
                ];
                $statusCount[$order->getModule()][$orderId][$sampleStatus] = isset($statusCount[$order->getModule()][$orderId][$sampleStatus]) ? $statusCount[$order->getModule()][$orderId][$sampleStatus] + 1 : 1;
            }
        }
        $returnArray = [];
        $returnArray['order'] = $orderSummary;
        $returnArray['sampleCount'] = $sampleCount;
        $returnArray['sampleStatusCount'] = $statusCount;
        return $returnArray;
    }

    private function getStoolTimePoint(array $formData, string $sampleStool): ?string
    {
        foreach (array_keys($this->getTimePoints()) as $timePoint) {
            if (in_array($sampleStool, $formData[$timePoint])) {
                return $timePoint;
            }
        }
        return null;
    }

    private function getNumericId(): string
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string) rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string) rand(0, 9);
        }
        return $id;
    }

    private function createOrderWithSamples(string $timePoint, string $orderType, array $samples, string $sampleGroup, bool $downtimeGenerated = false, ?DateTime $downtimeGeneratedCreatedTs = null): void
    {
        $nphOrder = $this->createOrder($timePoint, $orderType, null, $downtimeGenerated, $downtimeGeneratedCreatedTs);
        foreach ($samples as $sample) {
            $this->createSample($sample, $nphOrder, $sampleGroup);
        }
    }

    private function mapMetadata($metadata, $type, $values): string
    {
        return isset($metadata[$type]) ? array_search($metadata[$type], $values) : '';
    }

    private function saveSampleFinalization(array $formData, NphSample $sample, $biobankFinalization = false): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->saveSampleFinalizationData($formData, $sample, $biobankFinalization);
            $modifyType = $biobankFinalization ? NphSample::UNLOCK : null;
            if (!$this->sendToRdr($sample, $modifyType)) {
                throw new \Exception('Failed sending to RDR');
            }
            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollback();
            return false;
        }
    }

    private function saveSampleReFinalization(?string $notes, NphSample $sample): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            if ($notes) {
                $sample->setFinalizedNotes($notes);
            }
            $sample->setBiobankFinalized(true);
            $this->em->persist($sample);
            $this->em->flush();
            if (!$this->sendToRdr($sample, NphSample::UNLOCK, true)) {
                throw new \Exception('Failed sending to RDR');
            }
            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollback();
            return false;
        }
    }

    private function saveStoolSampleFinalization(array $formData, NphSample $sample, $biobankFinalization = false): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $order = $sample->getNphOrder();
            $nphSamples = $order->getNphSamples();
            $sampleCode = $sample->getSampleCode();
            $collectedTs = $formData["{$sampleCode}CollectedTs"];

            // Determine if collected time and questions are modified or not
            $isModified = false;
            if ($sample->getModifyType() === NphSample::UNLOCK) {
                $orderMetadata = json_decode($order->getMetadata(), true);
                if ($collectedTs != $sample->getCollectedTs() || $orderMetadata['bowelType'] !== $formData['bowelType']
                    || $orderMetadata['bowelQuality'] !== $formData['bowelQuality']) {
                    $isModified = true;
                }
            }

            // Update collected time for all samples that are collected
            foreach ($nphSamples as $nphSample) {
                if ($nphSample->getCollectedTs()) {
                    $nphSample->setCollectedTs($collectedTs);
                }
            }

            // Save finalized info for the current sample
            $nphSampleCode = $sample->getSampleCode();
            $notes = $formData["{$nphSampleCode}Notes"] ?? null;
            $this->saveNphSampleFinalizedInfo($sample, $collectedTs, $notes, null, $biobankFinalization);

            // Update order metadata
            $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType', 'bowelQuality', 'freezedTs']));
            $this->em->persist($order);
            $this->em->flush();

            // Send all finalized samples to RDR if modified
            if ($isModified) {
                foreach ($nphSamples as $nphSample) {
                    if ($nphSample->getRdrId() && $nphSample->getModifyType() !== NphSample::CANCEL &&
                        !$this->sendToRdr($nphSample, NphSample::UNLOCK)) {
                        throw new \Exception('Failed sending to RDR');
                    }
                }
                $connection->commit();
                return true;
            }
            // Send sample to RDR
            if ($this->sendToRdr($sample)) {
                $connection->commit();
                return true;
            }
            throw new \Exception('Failed sending to RDR');
        } catch (\Exception $e) {
            $connection->rollback();
        }
        return false;
    }

    private function saveSampleFinalizationData(array $formData, NphSample $sample, bool $biobankFinalization = false): void
    {
        $sampleModifyType = $sample->getModifyType();
        $sampleCode = $sample->getSampleCode();
        $aliquots = $this->getAliquots($sampleCode);
        if ($aliquots) {
            $this->saveNphAliquotFinalizedInfo($sample, $aliquots, $formData);
        }
        $sampleMetadata = '';
        if ($sample->getNphOrder()->getOrderType() === NphOrder::TYPE_URINE || $sample->getNphOrder()->getOrderType() === NphOrder::TYPE_24URINE) {
            $sampleMetadata = $this->jsonEncodeMetadata($formData, ['urineColor', 'urineClarity', 'totalCollectionVolume']);
        }
        $this->saveNphSampleFinalizedInfo($sample, $formData["{$sampleCode}CollectedTs"], $formData["{$sampleCode}Notes"], $sampleMetadata, $biobankFinalization);

        // Aliquot status is only set while editing a sample
        if ($sampleModifyType === NphSample::UNLOCK) {
            foreach ($sample->getNphAliquots() as $aliquot) {
                if (!empty($formData["cancel_{$aliquot->getAliquotCode()}_{$aliquot->getAliquotId()}"])) {
                    $aliquot->setStatus(NphSample::CANCEL);
                }
                if (!empty($formData["restore_{$aliquot->getAliquotCode()}_{$aliquot->getAliquotId()}"])) {
                    $aliquot->setStatus(NphSample::RESTORE);
                }
                $this->em->persist($aliquot);
                $this->em->flush();
            }
        }
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
    }

    private function saveNphSampleFinalizedInfo(NphSample $sample, DateTime $collectedTs, ?string $notes, ?string $sampleMetadata = null, $biobankFinalization = false): void
    {
        $sample->setCollectedTs($collectedTs);
        $sample->setCollectedTimezoneId($this->getTimezoneid());
        if (!$sample->getCollectedUser()) {
            $sample->setCollectedUser($this->user);
        }
        if (!$sample->getCollectedSite()) {
            $sample->setCollectedSite($this->site);
        }
        if ($notes) {
            $sample->setFinalizedNotes($notes);
        }
        if (!$sample->getFinalizedUser()) {
            $sample->setFinalizedUser($this->user);
        }
        if (!$sample->getFinalizedSite() && !$biobankFinalization) {
            $sample->setFinalizedSite($this->site);
        } elseif (!$sample->getFinalizedSite() && $biobankFinalization) {
            $sample->setFinalizedSite($sample->getCollectedSite());
        }
        if (!$sample->getFinalizedTs()) {
            $sample->setFinalizedTs(new DateTime());
        }
        if (!$sample->getFinalizedTimezoneId()) {
            $sample->setFinalizedTimezoneId($this->getTimezoneid());
        }
        if ($sampleMetadata) {
            $sample->setSampleMetadata($sampleMetadata);
        }
        $sample->setBiobankFinalized($biobankFinalization);
        $this->em->persist($sample);
        $this->em->flush();
    }

    private function saveNphAliquotFinalizedInfo(NphSample $sample, array $aliquots, array $formData): void
    {
        foreach ($aliquots as $aliquotCode => $aliquot) {
            if (isset($formData[$aliquotCode])) {
                foreach ($formData[$aliquotCode] as $key => $aliquotId) {
                    if ($aliquotId) {
                        $nphAliquot = $this->em->getRepository(NphAliquot::class)->findOneBy(['aliquotId' =>
                            $aliquotId]);
                        if (!$nphAliquot) {
                            $nphAliquot = new NphAliquot();
                            $nphAliquot->setNphSample($sample);
                            $nphAliquot->setAliquotId($aliquotId);
                            $nphAliquot->setAliquotCode($aliquotCode);
                            $nphAliquot->setUnits($aliquot['units']);
                        }
                        if (empty($formData["cancel_{$aliquotCode}_{$aliquotId}"]) && empty($formData["restore_{$aliquotCode}_{$aliquotId}"])) {
                            $nphAliquot->setAliquotTs($formData["{$aliquotCode}AliquotTs"][$key]);
                            $nphAliquot->setAliquotTimezoneId($this->getTimezoneid());
                            if (!empty($formData["{$aliquotCode}Volume"][$key])) {
                                $nphAliquot->setVolume($formData["{$aliquotCode}Volume"][$key]);
                            }
                        }
                        if (!empty($formData["${aliquotCode}glycerolAdditiveVolume"])) {
                            $nphAliquot->setAliquotMetadata(array_merge($nphAliquot->getAliquotMetadata(), ["${aliquotCode}glycerolAdditiveVolume" => $formData["${aliquotCode}glycerolAdditiveVolume"][$key]]));
                        }
                        $this->em->persist($nphAliquot);
                        $this->em->flush();
                        $this->loggerService->log(Log::NPH_ALIQUOT_CREATE, $nphAliquot->getId());
                    }
                }
            }
        }
    }

    private function saveSampleModificationsData(NphSample $sample, string $type, array $formData): void
    {
        if ($formData['reason'] === 'OTHER') {
            $formData['reason'] = $formData['otherText'];
        }
        $sample->setModifiedTs(new \DateTime());
        $sample->setModifiedTimezoneId($this->getTimezoneid());
        $sample->setModifiedSite($this->site);
        $sample->setModifiedUser($this->user);
        $sample->setModifyReason($formData['reason']);
        $sample->setModifyType($type);
        $this->em->persist($sample);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
    }

    private function sendToRdr(
        NphSample $sample,
        ?string $modifyType = null,
        bool $biobankUser = false,
        bool $adminUser = false
    ): bool {
        $order = $sample->getNphOrder();
        $modifyType = $modifyType ?? $sample->getModifyType();
        if ($modifyType === NphSample::UNLOCK) {
            $sampleRdrObject = $this->getRdrObject($order, $sample, 'amend', $biobankUser);
            if ($this->editRdrSample($order->getParticipantId(), $sample->getRdrId(), $sampleRdrObject)) {
                $sample->setModifyType(NphSample::EDITED);
                $sample->setModifiedUser($this->user);
                if ($biobankUser) {
                    $sample->setModifiedSite($sample->getFinalizedSite());
                    $sample->setModifyReason(NphSample::BIOBANK_MODIFY_REASON);
                } elseif ($adminUser) {
                    $sample->setModifiedSite($sample->getFinalizedSite());
                    $sample->setModifyReason(NphSample::NPH_ADMIN_MODIFY_REASON);
                } else {
                    $sample->setModifiedSite($this->site ?? $sample->getFinalizedSite());
                }
                $sample->setModifiedTs(new DateTime());
                $sample->setModifiedTimezoneId($this->getTimezoneid());
                $this->em->persist($sample);
                $this->em->flush();
                $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
                return true;
            }
        } else {
            $sampleRdrObject = $this->getRdrObject($order, $sample);
            $rdrId = $this->createRdrSample($order->getParticipantId(), $sampleRdrObject);
            if (!empty($rdrId)) {
                // Save RDR id
                $sample->setRdrId($rdrId);
                $this->em->persist($sample);
                $this->em->flush();
                $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
                return true;
            }
        }
        return false;
    }

    private function sendDLWToRdr(NphDlw $dlw): bool
    {
        $dlwRDRObject = $this->getDLWRDRObject($dlw);
        if ($dlw->getRdrId()) {
            $this->editRDRDlw($dlw->getNphParticipant(), $dlw->getRdrId(), $dlwRDRObject);
            $this->loggerService->log(Log::NPH_DLW_RDR_CREATE, $dlw->getRdrId());
        } else {
            $rdrId = $this->createRDRDlw($dlw->getNphParticipant(), $dlwRDRObject);
            $dlw->setRdrId($rdrId);
            $this->em->persist($dlw);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_DLW_RDR_UPDATE, $dlw->getRdrId());
        }
        return false;
    }

    private function createRDRDlw(string $participantId, \stdClass $rdrDlwObject): ?string
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/api/v1/nph/Participant/{$participantId}/DlwDosage", $rdrDlwObject);
            $result = json_decode($response->getBody()->getContents());
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return null;
        }
        return null;
    }

    private function editRDRDlw(string $participantId, string $rdrId, \stdClass $rdrDlwObject)
    {
        try {
            $response = $this->rdrApiService->put(
                "rdr/v1/api/v1/nph/Participant/{$participantId}/DlwDosage/{$rdrId}",
                $rdrDlwObject
            );
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result)) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    private function getDLWRDRObject(NphDlw $dlw): \stdClass
    {
        $object = new \stdClass();
        $object->module = $dlw->getModule();
        $object->visitperiod = $dlw->getVisitPeriod();
        $object->batchid = $dlw->getDoseBatchId();
        $object->participantweight = $dlw->getParticipantWeight();
        $object->dose = $dlw->getActualDose();
        $object->calculateddose = $dlw->getParticipantWeight() * 1.5;
        $object->dosetime = $dlw->getDoseAdministered()->format('Y-m-d\TH:i:s\Z');
        return $object;
    }

    private function createRdrSample(string $participantId, \stdClass $sampleObject): ?string
    {
        try {
            $response = $this->rdrApiService->post(
                "rdr/v1/api/v1/nph/Participant/{$participantId}/BiobankOrder",
                $sampleObject
            );
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return null;
        }
        return null;
    }

    private function editRdrSample(string $participantId, string $orderId, \stdClass $sampleObject): bool
    {
        try {
            $response = $this->rdrApiService->put(
                "rdr/v1/api/v1/nph/Participant/{$participantId}/BiobankOrder/{$orderId}",
                $sampleObject
            );
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result)) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    private function getUserSiteData(string $user, string $site): array
    {
        return [
            'author' => [
                'system' => 'https://www.pmi-ops.org/healthpro-username',
                'value' => $user
            ],
            'site' => [
                'system' => 'https://www.pmi-ops.org/site-id',
                'value' => $site
            ]
        ];
    }

    private function hasDuplicateIds(array $totalIds): bool
    {
        $totalIds = array_filter($totalIds, function ($value) {
            return $value !== null;
        });
        $uniqueIds = array_unique($totalIds);
        return count($totalIds) > count($uniqueIds);
    }

    private function getTimezoneid(): ?int
    {
        return $this->userService->getUserEntity()->getTimezoneId();
    }

    private function getDowntimeGeneratedOrdersByModuleAndVisit(string $ParticipantId, string $Module, string $Visit): array
    {
        $orders = $this->em->getRepository(NphOrder::class)->getDownTimeGeneratedOrdersByModuleAndVisit($ParticipantId, $Module, $Visit);
        return $orders;
    }
}
