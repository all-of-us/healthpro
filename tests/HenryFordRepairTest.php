<?php

namespace App\Tests;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\User;
use App\Service\HFHRepairService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use function Webmozart\Assert\Tests\StaticAnalysis\null;

class HenryFordRepairTest extends KernelTestCase
{
    private $em;
    private $HFHRepairService;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->HFHRepairService = static::getContainer()->get(HFHRepairService::class);
    }

    protected function getUser()
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        return $user;
    }

    public function getOrderData()
    {
        return [
            'user' => $this->getUser(),
            'site' => 'henryford',
            'finalizedSite' => 'henryford',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
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
    }

    public function getMeasurementData(): array
    {
        return [
            'site' => 'test-site1',
            'ts' => new \DateTime('2017-01-01', new \DateTimeZone('UTC')),
            'participantId' => 'P123456789',
            'finalizedSite' => 'henryford',
            'version' => '0.3.3'
        ];
    }

    public function createMeasurement($params = [], $user)
    {
        $measurement = new Measurement();
        $measurement->setUser($user);
        $measurement->setSite($params['site']);
        $measurement->setParticipantId($params['participantId']);
        $measurement->setCreatedTs($params['ts']);
        $measurement->setUpdatedTs($params['ts']);
        $measurement->setFinalizedUser($user);
        $measurement->setFinalizedSite($params['finalizedSite'] ?? $params['site']);
        $measurement->setFinalizedTs($params['finalizedTs'] ?? $params['ts']);
        $measurement->setVersion($params['version']);
        $measurement->setData(json_encode('{"test": "test"}'));
        return $measurement;
    }

    protected function createOrder($params = [])
    {
        $order = new Order();
        foreach ($params as $key => $value) {
            $order->{'set' . ucfirst($key)}($value);
        }
        return $order;
    }

    protected function addTestParticipantToCSV()
    {
        $CSVArray = file_get_contents('src/Cache/HFSitePairing.csv');
        $CSVArray = explode("\r\n", $CSVArray);
        $headers = array_slice($CSVArray, 0, 1, true);
        $allItems = array_slice($CSVArray, 1, null, true);
        $CSVArray = array_merge($headers, ['P123456789,TRANS_AM,TRANS_AM_HENRY_FORD,hpo-site-HenryFord,HPO-SITE-HENRYFORDDEARBORNUOPO'], $allItems);
        file_put_contents('src/Cache/HFSitePairing.csv', implode("\r\n", $CSVArray));
    }

    public function testRepair(): void
    {
        $user = $this->getUser();
        $this->em->persist($user);
        $this->em->flush();
        $testOrder = $this->createOrder($this->getOrderData());
        $testMeasurement = $this->createMeasurement($this->getMeasurementData(), $user);
        $this->assertSame('henryford', $testOrder->getFinalizedSite());
        $this->assertSame('henryford', $testMeasurement->getFinalizedSite());
        $CSVArrayBeforeAddition = file_get_contents('src/Cache/HFSitePairing.csv');
        $this->em->persist($testOrder);
        $this->em->persist($testMeasurement);
        $this->em->flush();
        $this->addTestParticipantToCSV();
        $this->HFHRepairService->repairHFHParticipants(1);
        $CSVArrayAfterRepair = file_get_contents('src/Cache/HFSitePairing.csv');
        $testMeasurement = $this->em->find(Measurement::class, $testMeasurement->getId());
        $testOrder = $this->em->find(Order::class, $testOrder->getId());
        $this->assertSame($CSVArrayBeforeAddition, $CSVArrayAfterRepair);
        $this->assertSame('henryforddearbornuopo', $testOrder->getFinalizedSite());
        $this->assertSame('henryforddearbornuopo', $testMeasurement->getFinalizedSite());
    }
}
