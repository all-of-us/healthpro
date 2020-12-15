<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderService
{
    protected $rdrApiService;
    protected $params;

    public function __construct(RdrApiService $rdrApiService, ParameterBagInterface $params)
    {
        $this->rdrApiService = $rdrApiService;
        $this->params = $params;
    }

    public function loadSamplesSchema($order)
    {
        $order->loadSamplesSchema($this->params);
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
}
