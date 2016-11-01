<?php
namespace Pmi\Drc;

class RdrOrders
{
    protected $rdrHelper;
    protected $client;
    protected static $resourceEndpoint = 'rdr/v1/';

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
    }

    protected function getClient()
    {
        if (!is_object($this->client)) {
            $this->client = $this->rdrHelper->getClient(self::$resourceEndpoint);
        }
        return $this->client;
    }

    public function createOrder($order)
    {
        try {
            $response = $this->getClient()->request('POST', "BiobankOrder", [
                'json' => $order
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function getOrder($orderId)
    {
        try {
            $response = $this->getClient()->request('GET', "BiobankOrder/{$orderId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
}
