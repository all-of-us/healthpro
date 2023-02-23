<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSite;
use App\Entity\Site;
use App\Entity\User;
use App\Form\Nph\NphOrderForm;
use App\Service\LoggerService;
use App\Service\RdrApiService;
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
    private $rdrApiService;

    private $module;
    private $visit;
    private $moduleObj;
    private $participantId;
    private $biobankId;
    private $user;
    private $site;

    private static $nonBloodTimePoints = ['preLMT', 'postLMT', 'preDSMT', 'postDSMT'];

    private static $placeholderSamples = ['STOOL'];

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
    }

    public function loadModules(string $module, string $visit, string $participantId, string $biobankId): void
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $this->moduleObj = new $moduleClass($visit);

        $this->module = $module;
        $this->visit = $visit;
        $this->participantId = $participantId;
        $this->biobankId = $biobankId;

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
                'disabled' => (bool)$sampleObj->getFinalizedTs()
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
        $addStoolKit = true;
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                if ($sample->getModifyType() !== NphSample::CANCEL) {
                    if (in_array($sample->getSampleCode(), $this->getSamplesByType('stool'))) {
                        if ($addStoolKit) {
                            $ordersData['stoolKit'] = $order->getOrderId();
                            $ordersData[$order->getTimepoint()][] = NphSample::SAMPLE_STOOL;
                            $addStoolKit = false;
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

    public function createOrdersAndSamples(array $formData): string
    {
        $sampleGroup = $this->generateSampleGroup();
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                if (in_array($timePoint, self::$nonBloodTimePoints)) {
                    $nailSamples = [];
                    foreach ($samples as $sample) {
                        if (in_array($sample, $this->getSamplesByType('nail'))) {
                            $nailSamples[] = $sample;
                        } elseif (!in_array($sample, self::$placeholderSamples)) {
                            $nphOrder = $this->createOrder($timePoint, $this->getSampleType($sample));
                            $this->createSample($sample, $nphOrder, $sampleGroup);
                        }
                    }
                    if (!empty($nailSamples)) {
                        $this->createOrderWithSamples($timePoint, 'nail', $nailSamples, $sampleGroup);
                    }
                } else {
                    $this->createOrderWithSamples($timePoint, 'blood', $samples, $sampleGroup);
                }
            }
        }
        // For stool kit samples
        if (!empty($formData['stoolKit'])) {
            // TODO: dynamically load stool visit type
            $nphOrder = $this->createOrder('preLMT', 'stool', $formData['stoolKit']);
            foreach ($this->getSamplesByType('stool') as $stoolSample) {
                if (!empty($formData[$stoolSample])) {
                    $this->createSample($stoolSample, $nphOrder, $sampleGroup, $formData[$stoolSample]);
                }
            }
        }
        return $sampleGroup;
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
        $nphOrder->setBiobankId($this->biobankId);
        $nphOrder->setUser($this->user);
        $nphOrder->setSite($this->site);
        $nphOrder->setCreatedTs(new DateTime());
        $nphOrder->setOrderType($orderType);
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

    private function createOrderWithSamples(string $timePoint, string $orderType, array $samples, string $sampleGroup): void
    {
        $nphOrder = $this->createOrder($timePoint, $orderType);
        foreach ($samples as $sample) {
            $this->createSample($sample, $nphOrder, $sampleGroup);
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
                $visitTypes = $module->getVisitTypes();
                $orderSummary[$order->getModule()]
                [$order->getVisitType()]
                [$order->getTimepoint()]
                [$module->getSampleType($sample->getSampleCode())]
                [$sample->getSampleCode()] = [
                    'sampleId' => $sample->getSampleID(),
                    'sampleName' => $sampleName,
                    'orderId' => $order->getOrderId(),
                    'healthProOrderId' => $order->getId(),
                    'createDate' => $order->getCreatedTs()->format('m/d/Y'),
                    'sampleStatus' => $sample->getStatus(),
                    'sampleCollectionVolume' => $sampleCollectionVolume,
                    'timepointDisplayName' => $timePointsDisplay[$order->getTimepoint()],
                    'sampleTypeDisplayName' => ucwords($module->getSampleType($sample->getSampleCode())),
                    'identifier' => $module->getSampleIdentifierFromCode($sample->getSampleCode()),
                    'visitDisplayName' => $visitTypes[$order->getVisitType()],
                    'sampleGroup' => $sample->getSampleGroup(),
                    'modifyType' => $sample->getModifyType(),
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

    public function saveSampleFinalization(array $formData, NphSample $sample): bool
    {
        $status = false;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->saveSampleFinalizationData($formData, $sample);
            // Send sample to RDR, throw exception if failed.
            if ($this->sendToRdr($sample)) {
                $status = true;
                $connection->commit();
            } else {
                throw new \Exception('Failed sending to RDR');
            }
        } catch (\Exception $e) {
            $connection->rollback();
            $this->em->refresh($sample);
        }
        return $status;
    }

    public function saveSampleFinalizationData(array $formData, NphSample $sample): void
    {
        $sampleModifyType = $sample->getModifyType();
        $sampleCode = $sample->getSampleCode();
        $aliquots = $this->getAliquots($sampleCode);
        if (!empty($aliquots)) {
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
                            $nphAliquot->setAliquotTs($formData["{$aliquotCode}AliquotTs"][$key]);
                            if (!empty($formData["{$aliquotCode}Volume"][$key])) {
                                $nphAliquot->setVolume($formData["{$aliquotCode}Volume"][$key]);
                            }
                            $this->em->persist($nphAliquot);
                            $this->em->flush();
                            $this->loggerService->log(Log::NPH_ALIQUOT_CREATE, $nphAliquot->getId());
                        }
                    }
                }
            }
        }
        $sample->setCollectedTs($formData["{$sampleCode}CollectedTs"]);
        if (empty($sample->getCollectedUser())) {
            $sample->setCollectedUser($this->user);
        }
        if (empty($sample->getCollectedSite())) {
            $sample->setCollectedSite($this->site);
        }
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
        $order = $sample->getNphOrder();
        if ($sample->getNphOrder()->getOrderType() === 'stool') {
            $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType', 'bowelQuality']));
            $this->em->persist($order);
            $this->em->flush();
        }

        // Aliquot status is only set while editing a sample
        if ($sampleModifyType === NphSample::UNLOCK) {
            foreach ($sample->getNphAliquots() as $aliquot) {
                if (!empty($formData["cancel_{$aliquot->getAliquotId()}"])) {
                    $aliquot->setStatus(NphSample::CANCEL);
                }
                if (!empty($formData["restore_{$aliquot->getAliquotId()}"])) {
                    $aliquot->setStatus(NphSample::RESTORE);
                }
                $this->em->persist($aliquot);
                $this->em->flush();
            }
        }
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
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

    public function saveSampleModification(array $formData, string $type, NphSample $sample): NphSample
    {
        $this->saveSampleModificationsData($sample, $type, $formData);
        return $sample;
    }

    private function saveSampleModificationsData(NphSample $sample, string $type, array $formData): void
    {
        if ($formData['reason'] === 'OTHER') {
            $formData['reason'] = $formData['otherText'];
        }
        $sample->setModifiedTs(new \DateTime());
        $sample->setModifiedSite($this->site);
        $sample->setModifiedUser($this->user);
        $sample->setModifyReason($formData['reason']);
        $sample->setModifyType($type);
        $this->em->persist($sample);
        $this->em->flush();
        $this->loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
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

    public function sendToRdr(NphSample $sample): bool
    {
        $order = $sample->getNphOrder();
        if ($sample->getModifyType() === NphSample::UNLOCK) {
            $sampleRdrObject = $this->getRdrObject($order, $sample, 'amend');
            if ($this->editRdrSample($order->getParticipantId(), $sample->getRdrId(), $sampleRdrObject)) {
                $sample->setModifyType(NphSample::EDITED);
                $this->em->persist($sample);
                $this->em->flush();
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
                return true;
            }
        }
        return false;
    }

    public function createRdrSample(string $participantId, \stdClass $sampleObject): ?string
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

    public function editRdrSample(string $participantId, string $orderId, \stdClass $sampleObject): bool
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

    public function getRdrObject(NphOrder $order, NphSample $sample, string $type = 'create'): \stdClass
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
        $obj->visitType = $order->getVisitType();
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
            $obj->amendedReason = $sample->getModifyReason();
            $obj->amendedInfo = $this->getUserSiteData(
                $sample->getModifiedUser()->getEmail(),
                NphSite::getSiteIdWithPrefix($sample->getModifiedSite())
            );
        }
        return $obj;
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
        if (empty($formData['stoolKit'])) {
            if ($hasSample === false) {
                $formErrors[] = [
                    'field' => 'checkAll',
                    'message' => 'Please select or enter at least one sample'
                ];
                return $formErrors;
            }
        } else {
            $nphOrder = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $formData['stoolKit']
            ]);
            if ($nphOrder) {
                $formErrors[] = [
                    'field' => 'stoolKit',
                    'message' => 'This Kit ID has already been used for another order'
                ];
            }
            $totalStoolTubes = [];
            foreach ($this->getSamplesByType('stool') as $stoolSample) {
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

    private function hasDuplicateIds(array $totalIds): bool
    {
        $totalIds = array_filter($totalIds, function ($value) {
            return $value !== null;
        });
        $uniqueIds = array_unique($totalIds);
        return count($totalIds) > count($uniqueIds);
    }
}
