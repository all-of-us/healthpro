<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSite;
use App\Entity\User;
use App\Form\Nph\NphOrderForm;
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
                'disabled' => $sampleObj->getFinalizedTs() || $sampleObj->getModifyType() === NphSample::CANCEL
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
                        $nphOrder = $this->createOrder($timePoint, $this->getSampleType($sample));
                        $this->createSample($sample, $nphOrder, $sampleGroup);
                    }
                }
                if (!empty($samplesByType['nail'])) {
                    $this->createOrderWithSamples($timePoint, NphOrder::TYPE_NAIL, $samplesByType['nail'], $sampleGroup);
                }
                if (!empty($samplesByType['blood'])) {
                    $this->createOrderWithSamples($timePoint, NphOrder::TYPE_BLOOD, $samplesByType['blood'], $sampleGroup);
                }
            }
        }
        // For stool kit samples
        if (!empty($formData['stoolKit'])) {
            $nphOrder = $this->createOrder($this->getStoolTimePoint($formData), NphOrder::TYPE_STOOL, $formData['stoolKit']);
            foreach ($this->getSamplesByType(NphOrder::TYPE_STOOL) as $stoolSample) {
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
        $nphOrder->setCreatedTimezoneId($this->getTimezoneid());
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
            foreach ($order->getNphSamples() as $nphSample) {
                $sampleCode = $nphSample->getSampleCode();
                if (isset($formData[$sampleCode])) {
                    if ($formData[$sampleCode]) {
                        $nphSample->setCollectedUser($this->user);
                        $nphSample->setCollectedSite($this->site);
                        $collectedTs = $orderType === NphOrder::TYPE_STOOL ? $formData[$orderType . 'CollectedTs'] : $formData[$sampleCode . 'CollectedTs'];
                        $nphSample->setCollectedTs($collectedTs);
                        $nphSample->setCollectedTimezoneId($this->getTimezoneid());
                        $nphSample->setCollectedNotes($formData[$sampleCode . 'Notes']);
                        if ($order->getOrderType() === NphOrder::TYPE_URINE) {
                            $nphSample->setSampleMetadata($this->jsonEncodeMetadata($formData, ['urineColor', 'urineClarity']));
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
            if ($orderType === NphOrder::TYPE_STOOL) {
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

    public function getExistingOrderCollectionData(NphOrder $order): array
    {
        $orderCollectionData = [];
        $orderType = $order->getOrderType();
        if ($orderType === NphOrder::TYPE_STOOL) {
            $orderCollectionData[$orderType . 'CollectedTs'] = $order->getCollectedTs();
        }
        foreach ($order->getNphSamples() as $nphSample) {
            $sampleCode = $nphSample->getSampleCode();
            if ($orderType !== NphOrder::TYPE_STOOL) {
                $orderCollectionData[$sampleCode . 'CollectedTs'] = $nphSample->getCollectedTs();
            }
            if ($nphSample->getCollectedTs()) {
                $orderCollectionData[$sampleCode] = true;
            }
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
        } elseif ($order->getOrderType() === 'urine') {
            $metadata = json_decode($order->getNphSamples()[0]->getSampleMetadata(), true);
            $metadata['urineColor'] = $this->mapMetadata($metadata, 'urineColor', NphOrderForm::$urineColors);
            $metadata['urineClarity'] = $this->mapMetadata($metadata, 'urineClarity', NphOrderForm::$urineClarity);
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
        if ($order->getOrderType() === NphOrder::TYPE_STOOL) {
            return $this->saveStoolSampleFinalization($formData, $sample, $biobankFinalization);
        }
        return $this->saveSampleFinalization($formData, $sample);
    }

    public function getExistingSampleData(NphSample $sample): array
    {
        $sampleData = [];
        $sampleCode = $sample->getSampleCode();
        $order = $sample->getNphOrder();
        $sampleData[$sampleCode . 'CollectedTs'] = $sample->getCollectedTs();
        $sampleData[$sampleCode . 'Notes'] = $sample->getFinalizedNotes();
        if ($order->getOrderType() === NphOrder::TYPE_URINE) {
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
        if ($order->getOrderType() === NphOrder::TYPE_STOOL) {
            $sampleData[$sampleCode . 'CollectedTs'] = $order->getCollectedTs();
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
                $sample->getModifiedUser() ? $sample->getModifiedUser()->getEmail() : $this->user->getEmail(),
                NphSite::getSiteIdWithPrefix($sample->getModifiedSite() ?? $this->site)
            );
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

    public function isDietStarted(array $moduleDietStatus): bool
    {
        $visitDiet = $this->getVisitDiet();
        if (!isset($moduleDietStatus[$visitDiet])) {
            return false;
        }
        return $moduleDietStatus[$visitDiet] === NphParticipant::DIET_STARTED;
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
                $module = new $moduleClass($order->getVisitType());
                $sampleName = $module->getSampleLabelFromCode($sample->getSampleCode());
                $timePointsDisplay = $module->getTimePoints();
                $sampleCollectionVolume = $module->getSampleCollectionVolumeFromCode($sample->getSampleCode());
                $visitTypes = $module->getVisitTypes();
                $sampleStatus = $sample->getStatus();
                $orderId = $order->getOrderId();
                $orderSummary[$order->getModule()]
                [$order->getVisitType()]
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
                    'sampleTypeDisplayName' => ucwords($module->getSampleType($sample->getSampleCode())),
                    'identifier' => $module->getSampleIdentifierFromCode($sample->getSampleCode()),
                    'visitDisplayName' => $visitTypes[$order->getVisitType()],
                    'sampleGroup' => $sample->getSampleGroup(),
                    'modifyType' => $sample->getModifyType(),
                    'orderStatus' => $order->getStatus(),
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

    private function getStoolTimePoint(array $formData): ?string
    {
        foreach (array_keys($this->getTimePoints()) as $timePoint) {
            if (in_array(NphSample::SAMPLE_STOOL, $formData[$timePoint])) {
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

    private function createOrderWithSamples(string $timePoint, string $orderType, array $samples, string $sampleGroup): void
    {
        $nphOrder = $this->createOrder($timePoint, $orderType);
        foreach ($samples as $sample) {
            $this->createSample($sample, $nphOrder, $sampleGroup);
        }
    }

    private function mapMetadata($metadata, $type, $values): string
    {
        return isset($metadata[$type]) ? array_search($metadata[$type], $values) : '';
    }

    private function saveSampleFinalization(array $formData, NphSample $sample): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->saveSampleFinalizationData($formData, $sample);
            if (!$this->sendToRdr($sample)) {
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
            $order->setMetadata($this->jsonEncodeMetadata($formData, ['bowelType', 'bowelQuality']));
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

    private function saveSampleFinalizationData(array $formData, NphSample $sample): void
    {
        $sampleModifyType = $sample->getModifyType();
        $sampleCode = $sample->getSampleCode();
        $aliquots = $this->getAliquots($sampleCode);
        if ($aliquots) {
            $this->saveNphAliquotFinalizedInfo($sample, $aliquots, $formData);
        }
        $sampleMetadata = '';
        if ($sample->getNphOrder()->getOrderType() === NphOrder::TYPE_URINE) {
            $sampleMetadata = $this->jsonEncodeMetadata($formData, ['urineColor', 'urineClarity']);
        }
        $this->saveNphSampleFinalizedInfo($sample, $formData["{$sampleCode}CollectedTs"], $formData["{$sampleCode}Notes"], $sampleMetadata);

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
        if (!$sample->getBiobankFinalized()) {
            $sample->setBiobankFinalized($biobankFinalization);
        }
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
                            $nphAliquot->setAliquotMetadata(array_merge($nphAliquot->getAliquotMetadata(), ["${aliquotCode}glycerolAdditiveVolume" => $formData["${aliquotCode}glycerolAdditiveVolume"]]));
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

    private function sendToRdr(NphSample $sample, $modifyType = null): bool
    {
        $order = $sample->getNphOrder();
        $modifyType = $modifyType ?? $sample->getModifyType();
        if ($modifyType === NphSample::UNLOCK) {
            $sampleRdrObject = $this->getRdrObject($order, $sample, 'amend');
            if ($this->editRdrSample($order->getParticipantId(), $sample->getRdrId(), $sampleRdrObject)) {
                $sample->setModifyType(NphSample::EDITED);
                $sample->setModifiedUser($this->user);
                $sample->setModifiedSite($this->site);
                $sample->setModifiedTs(new DateTime());
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
}
