<?php

namespace App\Tests\Entity;

use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NphTestCase extends KernelTestCase
{
    private $em;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

    }

    protected function getUser(): User
    {
        $user = new User;
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        return $user;
    }

    protected function createNphOrder($params = []): NphOrder
    {
        $order = new NphOrder();
        $this->setData($order, $params);
        return $order;
    }

    protected function createNphSample($params = []): NphSample
    {
        $sample = new NphSample();
        $this->setData($sample, $params);
        return $sample;
    }

    protected function createNphAliquot($params = []): NphAliquot
    {
        $aliquot = new NphAliquot();
        $this->setData($aliquot, $params);
        return $aliquot;
    }

    protected function createOrderAndSample(): NphSample
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        $sampleData = $this->getSampleData();
        $sampleData['nphOrder'] = $nphOrder;
        return $this->createNphSample($sampleData);
    }

    protected function getOrderData(): array
    {
        return [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
            'participantId' => 'P123456789',
            'orderId' => '0000000001',
            'module' => 1,
            'timepoint' => 'preLMT',
            'visitType' => 'LMT'
        ];
    }

    protected function getSampleData(): array
    {
        $ts = new \DateTime('2023-01-08 08:00:00');
        return [
            'sampleId' => '1000000000',
            'sampleCode' => 'SST8P5',
            'collectedTs' => $ts,
            'finalizedTs' => $ts
        ];
    }

    protected function getAliquotData(): array
    {
        return [
            'aliquotId' => '11111111111',
            'aliquotCode' => 'SST8P5A1',
            'volume' => 500,
            'units' => 'μL',
            'aliquotTs' => new \DateTime('2023-01-08 08:00:00')
        ];
    }

    private function setData($obj, $params): void
    {
        foreach ($params as $key => $value) {
            $obj->{'set' . ucfirst($key)}($value);
        }
    }
}
