<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\Site;
use App\Entity\User;
use App\Helper\PpscParticipant;
use App\Service\Ppsc\PpscApiService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;

class OrderService
{
    protected PpscApiService $ppscApiService;
    protected $params;
    protected $em;
    protected $mayolinkOrderService;
    protected $env;
    protected $userService;
    protected $siteService;
    protected $loggerService;
    protected Order $order;
    protected $participant;

    public function __construct(
        PpscApiService $ppscApiService,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        MayolinkOrderService $mayolinkOrderService,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService
    ) {
        $this->ppscApiService = $ppscApiService;
        $this->params = $params;
        $this->em = $em;
        $this->mayolinkOrderService = $mayolinkOrderService;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
    }

    public function loadSamplesSchema($order, PpscParticipant $participant = null, Measurement $physicalMeasurement = null)
    {
        $params = $this->getOrderParams(['order_samples_version', 'ml_mock_order', 'pediatric_order_samples_version']);
        $this->order = $order;
        $this->order->loadSamplesSchema($params, $participant, $physicalMeasurement);
    }

    public function setParticipant($participant)
    {
        $this->participant = $participant;
    }

    public function getParticipant()
    {
        return $this->participant;
    }

    public function createOrder(string $participantId, \stdClass $orderObject): string|bool
    {
        try {
            $response = $this->ppscApiService->post("participants/{$participantId}/biobank-orders", $orderObject);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->healthProOrderId)) {
                return $result->healthProOrderId;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return false;
        }
        return false;
    }

    public function editOrder($orderObject)
    {
        try {
            $response = $this->ppscApiService->put("participants/{$this->participant->id}/biobank-orders/{$this->order->getRdrId()}", $orderObject);
            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getOrdersByParticipant($participantId)
    {
        try {
            // Currently, PPSC API doesn't support this
            $response = $this->ppscApiService->get("rdr/v1/Participant/{$participantId}/BiobankOrder");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->data)) {
                return $result->data;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return [];
        }
        return [];
    }

    public function getOrders(array $query = []): array
    {
        try {
            // Currently, PPSC API doesn't support this
            $response = $this->ppscApiService->get('rdr/v1/BiobankOrder', [
                'query' => $query
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && is_array($result->data)) {
                return $result->data;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return [];
        }
        return [];
    }

    public function cancelRestoreOrder($orderObject)
    {
        try {
            $response = $this->ppscApiService->patch("participants/{$this->participant->id}/biobank-orders/{$this->order->getRdrId()}", $orderObject);
            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getOrder($participantId, $orderId)
    {
        try {
            $response = $this->ppscApiService->get("participants/{$participantId}/biobank-orders/{$orderId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->ppscApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getLabelsPdf()
    {
        // Always return true for mock orders
        if ($this->params->has('ml_mock_order')) {
            return ['status' => 'success'];
        }
        $result = ['status' => 'fail'];
        // Set collected time to created date at midnight local time
        $collectedAt = new DateTime($this->order->getCreatedTs()->format('Y-m-d'), new \DateTimeZone($this->userService->getUser()->getTimezone()));
        if ($site = $this->em->getRepository(Site::class)->findOneBy(['deleted' => 0, 'googleGroup' => $this->siteService->getSiteId()])) {
            $mayoClientId = $site->getMayolinkAccount();
        }
        // Check if mayo account number exists
        if (!empty($mayoClientId)) {
            $birthDate = $this->params->has('ml_real_dob') ? $this->participant->dob : $this->participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $this->order->getType(),
                'biobank_id' => $this->participant->biobankId,
                'first_name' => '*',
                'gender' => $this->participant->gender,
                'birth_date' => $birthDate,
                'order_id' => $this->order->getOrderId(),
                'collected_at' => $collectedAt->format('c'),
                'mayoClientId' => $mayoClientId,
                'requested_samples' => $this->order->getRequestedSamples(),
                'version' => $this->order->getVersion(),
                'tests' => $this->order->getSamplesInformation(),
                'salivaTests' => $this->order->getSalivaSamplesInformation()
            ];
            $pdf = $this->mayolinkOrderService->getLabelsPdf($options);
            if (!empty($pdf)) {
                $result['status'] = 'success';
                $result['pdf'] = $pdf;
            } else {
                $result['errorMessage'] = 'Error loading labels.';
            }
        } else {
            $result['errorMessage'] = 'A MayoLINK account number is not set for this site. Please contact an administrator.';
        }
        return $result;
    }

    public function cancelRestoreRdrOrder($type, $reason)
    {
        $order = $this->getCancelRestoreRdrObject($type, $reason);
        return $this->cancelRestoreOrder($order);
    }

    public function getCancelRestoreRdrObject($type, $reason)
    {
        $obj = new \StdClass();
        $statusType = $type === Order::ORDER_CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->amendedReason = $reason;
        $user = $this->order->getOrderUser($this->userService->getUser());
        $site = $this->order->getOrderSite($this->siteService->getRdrSite($this->siteService->getSiteId()));
        $obj->{$statusType . 'Info'} = $this->order->getOrderUserSiteData($user, $site);
        return $obj;
    }

    public function generateId()
    {
        $attempts = 0;
        $orderRepository = $this->em->getRepository(Order::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($orderRepository->findOneBy(['orderId' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (is_null($id)) {
            throw new \Exception('Failed to generate unique order id');
        }
        return $id;
    }

    public function setOrderUpdateFromForm($step, $form)
    {
        $formData = $form->getData();
        if ($formData["{$step}Notes"]) {
            $this->order->{'set' . ucfirst($step) . 'Notes'}($formData["{$step}Notes"]);
        } else {
            $this->order->{'set' . ucfirst($step) . 'Notes'}(null);
        }
        if ($step != 'processed') {
            if ($formData["{$step}Ts"]) {
                $this->order->{'set' . ucfirst($step) . 'Ts'}($formData["{$step}Ts"]);
                $this->order->{'set' . ucfirst($step) . 'TimezoneId'}($this->userService->getUserEntity()->getTimezoneId());
            } else {
                $this->order->{'set' . ucfirst($step) . 'Ts'}(null);
            }
        }
        if ($form->has("{$step}Samples")) {
            $hasSampleArray = $formData["{$step}Samples"] && is_array($formData["{$step}Samples"]);
            $samples = [];
            if ($hasSampleArray) {
                $samples = array_values($formData["{$step}Samples"]);
            }
            $this->order->{'set' . ucfirst($step) . 'Samples'}(json_encode($samples));
            if ($step === 'collected') {
                // Remove processed samples when not collected
                if (!empty($this->order->getProcessedSamplesTs())) {
                    $newProcessedSamples = $this->getNewProcessedSamples($samples);
                    $this->order->setProcessedSamples($newProcessedSamples['samples']);
                    $this->order->setProcessedSamplesTs($newProcessedSamples['timeStamps']);
                }
                // Remove finalized samples when not collected
                if (!empty($this->order->getFinalizedSamples())) {
                    $newFinalizedSamples = $this->getNewFinalizedSamples('collected', $samples);
                    $this->order->setFinalizedSamples($newFinalizedSamples);
                }
            }
            if ($step === 'processed') {
                $hasSampleTimeArray = $formData['processedSamplesTs'] && is_array($formData['processedSamplesTs']);
                if ($hasSampleArray && $hasSampleTimeArray) {
                    $processedSampleTimes = [];
                    foreach ($formData['processedSamplesTs'] as $sample => $dateTime) {
                        if ($dateTime && in_array($sample, $formData["{$step}Samples"])) {
                            $processedSampleTimes[$sample] = $dateTime->getTimestamp();
                        }
                    }
                    $this->order->setProcessedSamplesTs(json_encode($processedSampleTimes));
                } else {
                    $this->order->setProcessedSamplesTs(json_encode([]));
                }
                if ($this->order->getType() !== 'saliva' && !empty($formData['processedCentrifugeType'])) {
                    $this->order->setProcessedCentrifugeType($formData['processedCentrifugeType']);
                }
                // Remove finalized samples when not processed
                if (!empty($this->order->getFinalizedSamples())) {
                    $newFinalizedSamples = $this->getNewFinalizedSamples('processed', $samples);
                    $this->order->setFinalizedSamples($newFinalizedSamples);
                }
            }
        }
        if ($step === Order::ORDER_STEP_FINALIZED) {
            $this->order->setSubmissionTs(new \DateTime());
            if (isset($formData['fedexTracking'])) {
                $this->order->setFedexTracking($formData['fedexTracking']);
            }
        }
    }

    public function getNewProcessedSamples($samples)
    {
        $processedSamplesTs = json_decode($this->order->getProcessedSamplesTs(), true);
        $newProcessedSamples = [];
        $newProcessedSamplesTs = [];
        foreach ($processedSamplesTs as $sample => $timestamp) {
            // Check if each processed sample exists in collected samples list
            if (in_array($sample, $samples)) {
                $newProcessedSamples[] = $sample;
                $newProcessedSamplesTs[$sample] = $timestamp;
            }
        }
        return [
            'samples' => json_encode($newProcessedSamples),
            'timeStamps' => json_encode($newProcessedSamplesTs)
        ];
    }

    public function getNewFinalizedSamples($type, $samples)
    {
        $finalizedSamples = json_decode($this->order->getFinalizedSamples(), true);
        $newFinalizedSamples = [];
        if ($type === 'collected') {
            foreach ($finalizedSamples as $sample) {
                // Check if each finalized sample exists in collected samples list
                if (in_array($sample, $samples)) {
                    $newFinalizedSamples[] = $sample;
                }
            }
        } elseif ($type === 'processed') {
            // Determine processing samples which needs to be removed
            $processedSamples = array_intersect($finalizedSamples, Order::$samplesRequiringProcessing);
            $removeProcessedSamples = [];
            foreach ($processedSamples as $processedSample) {
                if (!in_array($processedSample, $samples)) {
                    $removeProcessedSamples[] = $processedSample;
                }
            }
            // Remove processing samples which are not processed
            if (!empty($removeProcessedSamples)) {
                foreach ($finalizedSamples as $key => $sample) {
                    if (in_array($sample, $removeProcessedSamples)) {
                        unset($finalizedSamples[$key]);
                    }
                }
            }
            $newFinalizedSamples = array_values($finalizedSamples);
        }
        return json_encode($newFinalizedSamples);
    }

    public function getOrderFormData($step)
    {
        $formData = [];
        if ($this->order->{'get' . ucfirst($step) . 'Notes'}()) {
            $formData["{$step}Notes"] = $this->order->{'get' . ucfirst($step) . 'Notes'}();
        }
        if ($step != 'processed') {
            if ($this->order->{'get' . ucfirst($step) . 'Ts'}()) {
                $formData["{$step}Ts"] = $this->order->{'get' . ucfirst($step) . 'Ts'}();
            }
        }
        if ($this->order->{'get' . ucfirst($step) . 'Samples'}()) {
            $samples = json_decode($this->order->{'get' . ucfirst($step) . 'Samples'}());
            if (is_array($samples) && count($samples) > 0) {
                $formData["{$step}Samples"] = $samples;
            }
        }
        if ($step == 'processed') {
            $processedSampleTimes = [];
            if (!empty($this->order->getProcessedSamplesTs())) {
                $processedSampleTimes = json_decode($this->order->getProcessedSamplesTs(), true);
            }
            foreach (Order::$samplesRequiringProcessing as $sample) {
                if (!empty($processedSampleTimes[$sample])) {
                    try {
                        $sampleTs = new DateTime();
                        $sampleTs->setTimestamp($processedSampleTimes[$sample]);
                        $sampleTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()));
                        $formData['processedSamplesTs'][$sample] = $sampleTs;
                    } catch (\Exception $e) {
                        $formData['processedSamplesTs'][$sample] = null;
                    }
                } else {
                    $formData['processedSamplesTs'][$sample] = null;
                }
            }
            if ($this->order->getProcessedCentrifugeType()) {
                $formData['processedCentrifugeType'] = $this->order->getProcessedCentrifugeType();
            }
        }
        if ($step === Order::ORDER_STEP_FINALIZED) {
            $formData['fedexTracking'] = $this->order->getFedexTracking();
        }
        return $formData;
    }

    public function sendOrderToMayo($mayoClientId)
    {
        // Return mock id for mock orders
        if ($this->params->has('ml_mock_order')) {
            return ['status' => 'success', 'mayoId' => $this->params->get('ml_mock_order')];
        }
        $result = ['status' => 'fail'];
        // Set collected time to user local time
        $collectedAt = new DateTime($this->order->getCollectedTs()->format('Y-m-d H:i:s'), new \DateTimeZone($this->userService->getUser()->getTimezone()));
        // Check if mayo account number exists
        if (!empty($mayoClientId)) {
            $birthDate = $this->params->has('ml_real_dob') ? $this->participant->dob : $this->participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $this->order->getType(),
                'biobank_id' => $this->participant->biobankId,
                'first_name' => '*',
                'gender' => $this->participant->gender,
                'birth_date' => $birthDate,
                'order_id' => $this->order->getOrderId(),
                'collected_at' => $collectedAt->format('c'),
                'mayoClientId' => $mayoClientId,
                'collected_samples' => $this->order->getFinalizedSamples(),
                'centrifugeType' => $this->order->getProcessedCentrifugeType(),
                'version' => $this->order->getVersion(),
                'tests' => $this->order->getSamplesInformation(),
                'salivaTests' => $this->order->getSalivaSamplesInformation()
            ];
            $mayoId = $this->mayolinkOrderService->createOrder($options);
            if (!empty($mayoId)) {
                $result['status'] = 'success';
                $result['mayoId'] = $mayoId;
            } else {
                $result['errorMessage'] = 'An error occurred while attempting to send this order. Please try again.';
            }
        } else {
            $result['errorMessage'] = 'Mayo account number is not set for this site. Please contact the administrator.';
        }
        return $result;
    }

    public function sendToRdr()
    {
        if ($this->order->getStatus() === Order::ORDER_UNLOCK) {
            return $this->editRdrOrder();
        }
        return $this->createRdrOrder();
    }

    public function createRdrOrder()
    {
        $orderRdrObject = $this->order->getRdrObject(
            $this->siteService->getRdrSite($this->order->getSite()),
            $this->siteService->getRdrSite($this->order->getCollectedSite()),
            $this->siteService->getRdrSite($this->order->getProcessedSite()),
            $this->siteService->getRdrSite($this->order->getFinalizedSite())
        );
        $rdrId = $this->createOrder($this->order->getParticipantId(), $orderRdrObject);
        if (!$rdrId) {
            // Check for rdr id conflict error code
            if ($this->ppscApiService->getLastErrorCode() === 409) {
                $rdrOrder = $this->getOrder($this->order->getParticipantId(), $this->order->getMayoId());
                // Check if order exists in RDR
                if (!empty($rdrOrder) && $rdrOrder->id === $this->order->getMayoId()) {
                    $rdrId = $this->order->getMayoId();
                }
            }
        }
        if (!empty($rdrId)) {
            // Save RDR id
            $this->order->setRdrId($rdrId);
            $this->em->persist($this->order);
            $this->em->flush();
            return true;
        }
        return false;
    }

    public function editRdrOrder()
    {
        $orderRdrObject = $this->order->getEditRdrObject(
            $this->siteService->getRdrSite($this->order->getSite()),
            $this->siteService->getRdrSite($this->order->getCollectedSite()),
            $this->siteService->getRdrSite($this->order->getProcessedSite()),
            $this->siteService->getRdrSite($this->order->getFinalizedSite()),
            $this->siteService->getRdrSite($this->order->getHistory()->getSite())
        );
        $status = $this->editOrder($orderRdrObject);
        if ($status) {
            return $this->createOrderHistory(Order::ORDER_EDIT);
        }
        return false;
    }

    public function getRequisitionPdf()
    {
        return $this->mayolinkOrderService->getRequisitionPdf($this->order->getMayoId());
    }

    // Returns sample's display text and color
    public function getCustomSamplesInfo()
    {
        $samples = [];
        $samplesInfo = $this->order->getType() === 'saliva' ? $this->order->getSalivaSamplesInformation() : $this->order->getSamplesInformation();
        foreach ($this->order->getCustomRequestedSamples() as $key => $value) {
            $sample = [
                'code' => $key,
                'color' => $samplesInfo[$value]['color'] ?? '',
                'number' => $samplesInfo[$value]['number'] ?? '',
                'label' => $samplesInfo[$value]['label'] ?? '',
                'sampleId' => $value
            ];
            if (isset($samplesInfo[$value]['icodeSwingingBucket']) && (empty($this->order->getType()) || $this->order->getType() === Order::ORDER_TYPE_DIVERSION)) {
                if ($this->order->getProcessedCentrifugeType() === Order::SWINGING_BUCKET) {
                    $sample['sampleId'] = $samplesInfo[$value]['icodeSwingingBucket'];
                } elseif ($this->order->getProcessedCentrifugeType() === Order::FIXED_ANGLE) {
                    $sample['sampleId'] = $samplesInfo[$value]['icodeFixedAngle'];
                }
            }
            if (!empty($this->order->getCollectedTs())) {
                $sample['collected_ts'] = $this->order->getCollectedTs();
            }
            if (!empty($this->order->getCollectedSamples()) && in_array($value, json_decode($this->order->getCollectedSamples()))) {
                $sample['collected_checked'] = true;
            }
            if (!empty($this->order->getFinalizedTs())) {
                $sample['finalized_ts'] = $this->order->getFinalizedTs();
            }
            if (!empty($this->order->getFinalizedSamples()) && in_array($value, json_decode($this->order->getFinalizedSamples()))) {
                $sample['finalized_checked'] = true;
            }
            if (!empty($this->order->getProcessedSamplesTs())) {
                $processedSamplesTs = json_decode($this->order->getProcessedSamplesTs(), true);
                if (!empty($processedSamplesTs[$value])) {
                    $processedTs = new DateTime();
                    $processedTs->setTimestamp($processedSamplesTs[$value]);
                    $processedTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()));
                    $sample['processed_ts'] = $processedTs;
                }
            }
            if (!empty($this->order->getProcessedSamples()) && in_array($value, json_decode($this->order->getProcessedSamples()))) {
                $sample['processed_checked'] = true;
            }
            if (in_array($value, Order::$samplesRequiringProcessing)) {
                $sample['process'] = true;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    public function createOrderHistory($type, $reason = '')
    {
        $status = false;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $orderHistory = new OrderHistory();
            $orderHistory->setReason($reason);
            $orderHistory->setOrderId($this->order);
            $userRepository = $this->em->getRepository(User::class);
            $orderHistory->setUser($userRepository->find($this->userService->getUser()->getId()));
            $orderHistory->setSite($this->siteService->getSiteId());
            $orderHistory->setType($type === Order::ORDER_REVERT ? Order::ORDER_ACTIVE : $type);
            $orderHistory->setCreatedTs(new DateTime());
            $orderHistory->setCreatedTimezoneId($this->userService->getUserEntity()->getTimezoneId());
            $orderHistory->setSamplesVersion($this->order->getVersion());
            $this->em->persist($orderHistory);
            $this->em->flush();
            $this->loggerService->log(Log::ORDER_HISTORY_CREATE, ['id' => $orderHistory->getId(), 'type' => $orderHistory->getType()]);

            // Update history id in order entity
            $this->order->setHistory($orderHistory);
            $this->em->persist($this->order);
            $this->em->flush();
            $this->loggerService->log(Log::ORDER_EDIT, $this->order->getId());
            $connection->commit();
            $status = true;
        } catch (\Exception $e) {
            $connection->rollback();
        }
        return $status;
    }

    /**
     * Revert collected, processed, finalized samples and timestamps
     */
    public function revertOrder()
    {
        // Get order object from RDR
        $object = $this->getOrder($this->participant->id, $this->order->getRdrId());

        if ($object === false) {
            return false;
        }

        //Update samples
        if (!empty($object->samples)) {
            foreach ($object->samples as $sample) {
                $sampleCode = $sample->test;
                if (!array_key_exists($sample->test, $this->order->getSamplesInformation()) && array_key_exists($sample->test, Order::$mapRdrSamples)) {
                    $sampleCode = Order::$mapRdrSamples[$sample->test]['code'];
                    $centrifugeType = Order::$mapRdrSamples[$sample->test]['centrifuge_type'];
                }
                if (!empty($sample->collected)) {
                    $collectedSamples[] = $sampleCode;
                    $collectedTs = $sample->collected;
                }
                if (!empty($sample->processed)) {
                    $processedSamples[] = $sampleCode;
                    $processedTs = new DateTime($sample->processed);
                    $processedSamplesTs[$sampleCode] = $processedTs->getTimestamp();
                }
                if (!empty($sample->finalized)) {
                    $finalizedSamples[] = $sampleCode;
                    $finalizedTs = $sample->finalized;
                }
            }
        }
        // Update notes field
        $collectedNotes = !empty($object->notes->collected) ? $object->notes->collected : null;
        $processedNotes = !empty($object->notes->processed) ? $object->notes->processed : null;
        $finalizedNotes = !empty($object->notes->finalized) ? $object->notes->finalized : null;
        // Update tracking number
        if (!empty($object->identifier)) {
            foreach ($object->identifier as $identifier) {
                if (preg_match('/tracking-number/i', $identifier->system)) {
                    $trackingNumber = $identifier->value;
                    break;
                }
            }
        }
        $lastUnlockedOrderHistory = $this->em->getRepository(OrderHistory::class)->getLastOrderHistoryUnlocked($this->order->getId());
        if ($lastUnlockedOrderHistory->getSamplesVersion() !== null) {
            $orderVersion = $lastUnlockedOrderHistory->getSamplesVersion();
        } else {
            $orderVersion = $this->order->getVersion();
        }
        $status = false;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->order->setCollectedSamples(json_encode(!empty($collectedSamples) ? $collectedSamples : []));
            $this->order->setCollectedTs(!empty($collectedTs) ? new DateTime($collectedTs) : null);
            $this->order->setProcessedSamples(json_encode(!empty($processedSamples) ? $processedSamples : []));
            $this->order->setProcessedSamplesTs(json_encode(!empty($processedSamplesTs) ? $processedSamplesTs : []));
            $this->order->setFinalizedSamples(json_encode(!empty($finalizedSamples) ? $finalizedSamples : []));
            $this->order->setFinalizedTs(!empty($finalizedTs) ? new DateTime($finalizedTs) : null);
            $this->order->setCollectedNotes($collectedNotes);
            $this->order->setProcessedNotes($processedNotes);
            $this->order->setFinalizedNotes($finalizedNotes);
            $this->order->setFedexTracking(!empty($trackingNumber) ? $trackingNumber : null);
            $this->order->setVersion($orderVersion);
            //Update centrifuge type
            if (!empty($centrifugeType)) {
                $this->order->setProcessedCentrifugeType($centrifugeType);
            }
            $this->em->persist($this->order);
            $this->em->flush();
            $this->createOrderHistory(Order::ORDER_REVERT);
            $connection->commit();
            $status = true;
        } catch (\Exception $e) {
            $connection->rollback();
        }
        return $status;
    }

    public function canEdit()
    {
        // Allow cohort 1 and 2 participants to edit existing orders even if status is false
        return !$this->participant->status && !empty($this->order->getId()) ? $this->participant->editExistingOnly : $this->participant->status;
    }

    public function loadFromJsonObject(stdClass $object)
    {
        if (!empty($object->samples)) {
            foreach ($object->samples as $sample) {
                $sampleCode = $sample->test;
                if (!array_key_exists($sample->test, $this->order->getSamplesInformation()) && array_key_exists($sample->test, Order::$mapRdrSamples)) {
                    $sampleCode = Order::$mapRdrSamples[$sample->test]['code'];
                    $centrifugeType = Order::$mapRdrSamples[$sample->test]['centrifuge_type'];
                }
                if (!empty($sample->collected)) {
                    $collectedSamples[] = $sampleCode;
                    $collectedTs = $sample->collected;
                }
                if (!empty($sample->processed)) {
                    $processedSamples[] = $sampleCode;
                    $processedTs = $sample->processed;
                    $processedSamplesTs[$sampleCode] = (new DateTime($sample->processed))->getTimestamp();
                }
                if (!empty($sample->finalized)) {
                    $finalizedSamples[] = $sampleCode;
                    $finalizedTs = $sample->finalized;
                }
            }
        }

        // Update notes field
        $collectedNotes = !empty($object->notes->collected) ? $object->notes->collected : null;
        $processedNotes = !empty($object->notes->processed) ? $object->notes->processed : null;
        $finalizedNotes = !empty($object->notes->finalized) ? $object->notes->finalized : null;

        if (!empty($object->identifier)) {
            foreach ($object->identifier as $identifier) {
                if (preg_match('/tracking-number/i', $identifier->system)) {
                    $trackingNumber = $identifier->value;
                }
                if (preg_match('/kit-id/i', $identifier->system)) {
                    $kitId = $identifier->value;
                }
            }
        }

        // Extract participantId
        preg_match('/^Patient\/(P\d+)$/i', $object->subject, $subject_matches);
        $participantId = $subject_matches[1];

        $this->order->setParticipantId($participantId);
        if (!empty($kitId)) {
            $this->order->setOrderId($kitId);
        }
        // Can be used as order Id
        $this->order->setRdrId($object->id);
        if (property_exists($object, 'biobankId')) {
            $this->order->setBiobankId($object->biobankId);
        }
        $this->order->setType('kit');
        if (!empty($object->created)) {
            $this->order->setCreatedTs(new DateTime($object->created));
        }
        if (!empty($processedTs)) {
            $this->order->setProcessedTs(new DateTime($processedTs));
        }
        if (!empty($collectedTs)) {
            $this->order->setCollectedTs(new DateTime($collectedTs));
        }
        if (!empty($finalizedTs)) {
            $this->order->setFinalizedTs(new DateTime($finalizedTs));
        }
        $this->order->setProcessedCentrifugeType((!empty($centrifugeType)) ? $centrifugeType : null);
        $this->order->setCollectedSamples(json_encode(!empty($collectedSamples) ? $collectedSamples : []));
        $this->order->setProcessedSamples(json_encode(!empty($processedSamples) ? $processedSamples : []));
        $this->order->setProcessedSamplesTs(json_encode(!empty($processedSamplesTs) ? $processedSamplesTs : []));
        $this->order->setFinalizedSamples(json_encode(!empty($finalizedSamples) ? $finalizedSamples : []));
        $this->order->setQuanumFinalizedSamples(!empty($finalizedSamples) ? join(',', $finalizedSamples) : '');
        $this->order->setCollectedNotes($collectedNotes);
        $this->order->setProcessedNotes($processedNotes);
        $this->order->setFinalizedNotes($finalizedNotes);
        $this->order->setFedexTracking(!empty($trackingNumber) ? $trackingNumber : null);
        $this->order->setOrigin($object->origin);
        if (!empty($object->collectedInfo)) {
            $this->order->setQuanumCollectedUser($object->collectedInfo->author->value);
        }
        if ($object->collectedInfo instanceof stdClass && !empty($object->collectedInfo->address)) {
            $this->order->setCollectedSiteAddress($object->collectedInfo->author->value);
        }
        $this->order->setCollectedSiteName('A Quest Site');
        if (!empty($object->processedInfo)) {
            $this->order->setQuanumProcessedUser($object->processedInfo->author->value);
        }
        $this->order->setProcessedSiteName('A Quest Site');
        if (!empty($object->finalizedInfo)) {
            $this->order->setQuanumFinalizedUser($object->finalizedInfo->author->value);
        }
        $this->order->setFinalizedSiteName('A Quest Site');
        $this->order->setQuanumOrderStatus('Finalized');
        if ($this->params->has('order_samples_version')) {
            $this->order->setVersion($this->params->get('order_samples_version'));
        }
        return $this->order;
    }

    public function updateOrderVersion(Order $order, string $orderVersion, FormInterface $orderCollectForm): Order
    {
        $processedSamples = json_decode($order->getProcessedSamples(), true);
        $processedSamplesTs = json_decode($order->getProcessedSamplesTs(), true);
        $finalizedSamples = json_decode($order->getFinalizedSamples(), true);
        $collectedSamples = json_decode($order->getCollectedSamples(), false);
        if ($order->getVersion() === '3.1') {
            if (!empty($collectedSamples)) {
                in_array('1PS08', $collectedSamples) ? array_push($collectedSamples, 'PS04A', 'PS04B') : null;
                $collectedSamples = array_diff($collectedSamples, ['1PS08']);
                $collectedSamples = array_values($collectedSamples);
            }
            if (!empty($processedSamples)) {
                in_array('1PS08', $processedSamples) ? array_push($processedSamples, 'PS04A', 'PS04B') : null;
                $processedSamples = array_diff($processedSamples, ['1PS08']);
                $processedSamples = array_values($processedSamples);
            }
            if (!empty($processedSamplesTs) && array_key_exists('1PS08', $processedSamplesTs)) {
                $processedSamplesTs['PS04A'] = $processedSamplesTs['1PS08'];
                $processedSamplesTs['PS04B'] = $processedSamplesTs['1PS08'];
                unset($processedSamplesTs['1PS08']);
            }
            if (!empty($finalizedSamples)) {
                in_array('1PS08', $finalizedSamples) ? array_push($finalizedSamples, 'PS04A', 'PS04B') : null;
                $finalizedSamples = array_diff($finalizedSamples, ['1PS08']);
                $finalizedSamples = array_values($finalizedSamples);
            }
        } elseif ($order->getVersion() === '3.2') {
            if (!empty($collectedSamples)) {
                in_array('PS04A', $collectedSamples) || in_array('PS04B', $collectedSamples) ? array_push($collectedSamples, '1PS08') : null;
                $collectedSamples = array_diff($collectedSamples, ['PS04A', 'PS04B']);
                $collectedSamples = array_values($collectedSamples);
            }
            if (!empty($processedSamples)) {
                in_array('PS04A', $processedSamples) || in_array('PS04B', $processedSamples) ? array_push($processedSamples, '1PS08') : null;
                $processedSamples = array_diff($processedSamples, ['PS04A', 'PS04B']);
                $processedSamples = array_values($processedSamples);
            }
            if (!empty($processedSamplesTs)) {
                if (array_key_exists('PS04A', $processedSamplesTs)) {
                    $processedSamplesTs['1PS08'] = $processedSamplesTs['PS04A'];
                    unset($processedSamplesTs['PS04A']);
                }
                if (array_key_exists('PS04B', $processedSamplesTs)) {
                    if (array_key_exists('1PS08', $processedSamplesTs)) {
                        $processedSamplesTs['1PS08'] = min($processedSamplesTs['1PS08'], $processedSamplesTs['PS04B']);
                    } else {
                        $processedSamplesTs['1PS08'] = $processedSamplesTs['PS04B'];
                    }
                    unset($processedSamplesTs['PS04B']);
                }
            }
            if (!empty($finalizedSamples)) {
                in_array('PS04A', $finalizedSamples) || in_array('PS04B', $finalizedSamples) ? array_push($finalizedSamples, '1PS08') : null;
                $finalizedSamples = array_diff($finalizedSamples, ['PS04A', 'PS04B']);
                $finalizedSamples = array_values($finalizedSamples);
            }
        }
        $order->setVersion($orderVersion);
        $order->setType(Order::ORDER_TYPE_KIT);
        if (!empty($collectedSamples)) {
            $order->setCollectedSamples(json_encode($collectedSamples));
        }
        if (!empty($processedSamples)) {
            $order->setProcessedSamples(json_encode($processedSamples));
        }
        if (!empty($processedSamplesTs)) {
            $order->setProcessedSamplesTs(json_encode($processedSamplesTs));
        }
        if (!empty($finalizedSamples)) {
            $order->setFinalizedSamples(json_encode($finalizedSamples));
        }
        $this->loadSamplesSchema($order);
        $this->em->persist($order);
        $this->em->flush();
        return $order;
    }

    public function inactiveSiteFormDisabled(): bool
    {
        if ($this->order->getStatus() === Order::ORDER_UNLOCK) {
            return false;
        }
        return !$this->siteService->isActiveSite();
    }

    protected function getOrderParams($fields)
    {
        $params = [];
        foreach ($fields as $field) {
            if ($this->params->has($field) && !empty($this->params->get($field))) {
                $params[$field] = $this->params->get($field);
            }
        }
        return $params;
    }

    private function getNumericId()
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string) rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string) rand(0, 9);
        }
        return $id;
    }
}
