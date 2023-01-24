<?php

namespace App\Tests\Repository;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Repository\NphOrderRepository;

class NphOrderRepositoryTest extends RepositoryTestCase
{
    private $repo;
    private $nphOrder;

    public function setup(): void
    {
        parent::setUp();
        $this->repo = static::$container->get(NphOrderRepository::class);
        $this->nphOrder = $this->createTestOrder();
    }

    private function createTestOrder(): NphOrder
    {
        $user = $this->getUser();
        $siteId = $this->getSite()->getGoogleGroup();
        $nphOrder = new NphOrder();
        $nphOrder->setModule(1);
        $nphOrder->setVisitType('LMT');
        $nphOrder->setTimepoint('preLMT');
        $nphOrder->setOrderId('100000001');
        $nphOrder->setParticipantId('P000000001');
        $nphOrder->setUser($user);
        $nphOrder->setSite($siteId);
        $nphOrder->setCreatedTs(new \DateTime());
        $nphOrder->setOrderType('urine');
        $this->em->persist($nphOrder);
        $this->em->flush();

        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId('100000002');
        $nphSample->setSampleCode('URINES');
        $nphSample->setSampleGroup('100000008');
        $this->em->persist($nphSample);
        $this->em->flush();
        $nphSample2 = new NphSample();
        $nphSample2->setNphOrder($nphOrder);
        $nphSample2->setSampleId('100000003');
        $nphSample2->setSampleCode('URINES');
        $nphSample2->setSampleGroup('100000008');
        $this->em->persist($nphSample2);
        $this->em->flush();
        return $nphOrder;
    }


    public function testGetOrdersByVisitType(): void
    {
        $orders = $this->repo->getOrdersByVisitType('P000000001', 'LMT');
        $this->assertSame($this->nphOrder, $orders[0]);
    }

    public function testGetOrdersBySampleGroup(): void
    {
        $orders = $this->repo->getOrdersBySampleGroup('P000000001', '100000008');
        $this->assertSame($this->nphOrder, $orders[0]);
    }
}
