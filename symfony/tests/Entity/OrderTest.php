<?php

namespace App\Test\Service;

use App\Entity\Order;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    protected function getUser()
    {
        $user = new User;
        $user->setEmail('test@example.com');
        return $user;
    }

    public function createOrder($params = [])
    {
        $order = new Order;
        $order->setUser($params['user']);
        $order->setSite($params['site']);
        $order->setParticipantId($params['participantId']);
        $order->setRdrId($params['rdrId']);
        $order->setBiobankId($params['biobankId']);
        $order->setCreatedTs($params['ts']);
        $order->setOrderId($params['orderId']);
        $order->setMayoId($params['mayoId']);
        $order->setPrintedTs($params['printedTs'] ?? $params['ts']);
        $order->setCollectedUser($params['collectedUser'] ?? $params['user']);
        $order->setCollectedSite($params['collectedSite'] ?? $params['site']);
        $order->setCollectedTs($params['collectedTs'] ?? $params['ts']);
        $order->setCollectedSamples($params['collectedSamples']);
        $order->setProcessedUser($params['processedUser'] ?? $params['user']);
        $order->setProcessedSite($params['processedSite'] ?? $params['site']);
        $order->setProcessedTs($params['processedTs'] ?? $params['ts']);
        $order->setProcessedSamples($params['processedSamples']);
        $order->setProcessedSamplesTs($params['processedSamplesTs']);
        $order->setProcessedCentrifugeType($params['processedCentrifugeType']);
        $order->setFinalizedUser($params['finalizedUser'] ?? $params['user']);
        $order->setFinalizedSite($params['finalizedSite'] ?? $params['site']);
        $order->setFinalizedTs($params['finalizedTs'] ?? $params['ts']);
        $order->setFinalizedSamples($params['finalizedSamples']);
        $order->setVersion($params['version']);
        return $order;
    }

    public function testRdrObject()
    {
        $orderArray = [
            'user' => $this->getUser(),
            'site' => 'test',
            'ts' => new \DateTime('2020-12-21 08:00:00'),
            'participantId' => 'P123456789',
            'rdrId' => 'WEB123456789',
            'biobankId' => 'Y123456789',
            'orderId' => '0123456789',
            'mayoId' => 'WEB123456789',
            'collectedSamples' => '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]',
            'processedSamples' => '["1SS08","1PS08"]',
            'processedSamplesTs' => '{"1SS08":1606753560,"1PS08":1606753560}',
            'processedCentrifugeType' => 'swinging_bucket',
            'finalizedSamples' => '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]',
            'version' => '3.1'
        ];
        // HPO order
        $order = $this->createOrder($orderArray);
        $order->loadSamplesSchema();
        $this->assertEquals('Patient/P123456789', $order->getRdrObject()->subject);
        // Assert createdInfo
        $this->assertEquals('test@example.com', $order->getRdrObject()->createdInfo['author']['value']);
        $this->assertEquals('hpo-site-test', $order->getRdrObject()->createdInfo['site']['value']);
        // Assert identifiers orderId and mayoId
        $this->assertEquals('0123456789', $order->getRdrObject()->identifier[0]['value']);
        $this->assertEquals('WEB123456789', $order->getRdrObject()->identifier[1]['value']);
        // Assert processed sample codes
        $this->assertEquals('1SST8', $order->getRdrObject()->samples[0]['test']);
        $this->assertEquals('1PST8', $order->getRdrObject()->samples[1]['test']);
        // Assert processingRequired
        $this->assertEquals('1', $order->getRdrObject()->samples[0]['processingRequired']);
        $this->assertEquals('1', $order->getRdrObject()->samples[1]['processingRequired']);

        // DV order
        $orderArray['type'] = 'kit';
        $orderArray['processedCentrifugeType'] = 'fixed_angle';
        $order = $this->createOrder($orderArray);
        $order->loadSamplesSchema();
        // Assert processed sample codes
        $this->assertEquals('2SST8', $order->getRdrObject()->samples[0]['test']);
        $this->assertEquals('2PST8', $order->getRdrObject()->samples[1]['test']);
    }
}
