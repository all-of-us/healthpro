<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderService
{
    const ORDER_CANCEL_STATUS = 'CANCELLED';
    const ORDER_RESTORE_STATUS = 'UNSET';
    const ORDER_EDIT_STATUS = 'AMENDED';

    protected $rdrApiService;
    protected $params;
    protected $em;
    protected $mayolinkOrderService;
    protected $env;
    protected $siteService;
    protected $order;
    protected $participant;

    public function __construct(
        RdrApiService $rdrApiService,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        MayolinkOrderService $mayolinkOrderService,
        UserService $userService,
        SiteService $siteService
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->params = $params;
        $this->em = $em;
        $this->mayolinkOrderService = $mayolinkOrderService;
        $this->userService = $userService;
        $this->mayolinkOrderService = $mayolinkOrderService;
        $this->siteService = $siteService;
    }

    public function loadSamplesSchema($order)
    {
        $params = $this->getOrderParams(['order_samples_version', 'ml_mock_order']);
        $this->order = $order;
        $this->order->loadSamplesSchema($params);
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

    public function setParticipant($participant)
    {
        $this->participant = $participant;
    }

    public function getParticipant()
    {
        return $this->participant;
    }

    public function createOrder($participantId, $order)
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/BiobankOrder", $order);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function editOrder($participantId, $orderId, $order)
    {
        try {
            $result = $this->getOrder($participantId, $orderId);
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/BiobankOrder/{$orderId}", [
                'json' => $order,
                'headers' => ['If-Match' => $result->meta->versionId]
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->status) && $result->status === self::ORDER_EDIT_STATUS) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getOrder($participantId, $orderId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/Participant/{$participantId}/BiobankOrder/{$orderId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
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
        $collectedAt = new \DateTime($this->order->getCreatedTs()->format('Y-m-d'), new \DateTimeZone($this->userService->getUser()->getInfo()['timezone']));
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

    public function cancelRestoreOrder($type, $orderObject)
    {
        try {
            $result = $this->getOrder($this->participant->id, $this->order->getRdrId());
            $response = $this->rdrApiService->patch("Participant/{$this->participant->id}/BiobankOrder/{$this->order->getRdrId()}", [
                'json' => $orderObject,
                'headers' => ['If-Match' => $result->meta->versionId]
            ]);
            $result = json_decode($response->getBody()->getContents());
            $rdrStatus = $type === Order::ORDER_CANCEL ? self::ORDER_CANCEL_STATUS : self::ORDER_RESTORE_STATUS;
            if (is_object($result) && isset($result->status) && $result->status === $rdrStatus) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }


    public function cancelRestoreRdrOrder($type, $reason)
    {
        $order = $this->getCancelRestoreRdrObject($type, $reason);
        return $this->cancelRestoreOrder($type, $order);
    }

    public function getCancelRestoreRdrObject($type, $reason)
    {
        $obj = new \StdClass();
        $statusType = $type === Order::ORDER_CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->amendedReason = $reason;
        $user = $this->getOrderUser($this->userService->getUser()->getId());
        $site = $this->getOrderSite($this->siteService->getSiteId());
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

    private function getNumericId()
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string)rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string)rand(0, 9);
        }
        return $id;
    }

    public function setOrderUpdateFromForm($step, $form)
    {
        $formData = $form->getData();
        if ($formData["{$step}Notes"]) {
            $updateArray["{$step}Notes"] = $formData["{$step}Notes"];
            $this->order->{'set' . ucfirst($step) . 'Notes'}($formData["{$step}Notes"]);
        } else {
            $this->order->{'set' . ucfirst($step) . 'Notes'}(null);
        }
        if ($step != 'processed') {
            if ($formData["{$step}Ts"]) {
                $this->order->{'set' . ucfirst($step) . 'Ts'}($formData["{$step}Ts"]);
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
                if ($this->order->getType() !== 'saliva' && !empty($formData["processedCentrifugeType"])) {
                    $this->order->setProcessedCentrifugeType($formData["processedCentrifugeType"]);
                }
                // Remove finalized samples when not processed
                if (!empty($this->order->getFinalizedSamples())) {
                    $newFinalizedSamples = $this->getNewFinalizedSamples('processed', $samples);
                    $this->order->setFinalizedSamples($newFinalizedSamples);
                }
            }
        }
        if ($step === 'finalized' && ($this->order->getType() === 'kit' || $this->order->getType() === 'diversion')) {
            $this->order->getFedexTracking($formData['fedexTracking']);
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
        };
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
                        $sampleTs = new \DateTime();
                        $sampleTs->setTimestamp($processedSampleTimes[$sample]);
                        $sampleTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getInfo()['timezone']));
                        $formData['processedSamplesTs'][$sample] = $sampleTs;
                    } catch (\Exception $e) {
                        $formData['processedSamplesTs'][$sample] = null;
                    }
                } else {
                    $formData['processedSamplesTs'][$sample] = null;
                }
            }
            if ($this->order->getProcessedCentrifugeType()) {
                $formData["processedCentrifugeType"] = $this->order->getProcessedCentrifugeType();
            }
        }
        if ($step === 'finalized' && ($this->order->getType() === 'kit' || $this->order->getType() === 'diversion')) {
            $formData['fedexTracking'] = $this->order->getFedextTracking();
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
        $collectedAt = new \DateTime($this->order->getCollectedTs()->format('Y-m-d H:i:s'), new \DateTimeZone($this->userService->getUser()->getInfo()['timezone']));
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
        //Todo Implement Edit Order
        return $this->createRdrOrder();
    }

    public function createRdrOrder()
    {
        $orderRdrObject = $this->order->getRdrObject();
        $rdrId = $this->createOrder($this->order->getParticipantId(), $orderRdrObject);
        if (!$rdrId) {
            // Check for rdr id conflict error code
            if ($this->rdrApiService->getLastErrorCode() === 409) {
                $rdrOrder = $this->getOrder($this->order->getParticipantId(), $this->getMayoId());
                // Check if order exists in RDR
                if (!empty($rdrOrder) && $rdrOrder->id === $this->order['mayo_id']) {
                    $rdrId = $this->getMayoId();
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

    public function getRequisitionPdf()
    {
        return $this->mayolinkOrderService->getRequisitionPdf($this->order->getMayoId());
    }

    public function getCustomSamplesInfo()
    {
        $samples = [];
        $samplesInfo = $this->order->getType() === 'saliva' ? $this->order->getSalivaSamplesInformation() : $this->order->getSamplesInformation();
        foreach ($this->order->getCustomRequestedSamples() as $key => $value) {
            $sample = [
                'code' => $key,
                'color' => isset($samplesInfo[$value]['color']) ? $samplesInfo[$value]['color'] : ''
            ];
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
                    $processedTs = new \DateTime();
                    $processedTs->setTimestamp($processedSamplesTs[$value]);
                    $processedTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getInfo()['timezone']));
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
}
