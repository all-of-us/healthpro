<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderService
{
    protected $rdrApiService;
    protected $params;
    protected $em;
    protected $mayolinkOrderService;
    protected $env;
    protected $siteService;
    protected $order;

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

    public function getLabelsPdf($participant)
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
            $birthDate = $this->params->has('ml_real_dob') ? $participant->dob : $participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $this->order->getType(),
                'biobank_id' => $participant->biobankId,
                'first_name' => '*',
                'gender' => $participant->gender,
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
}
