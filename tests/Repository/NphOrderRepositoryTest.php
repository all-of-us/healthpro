<?php

namespace App\Tests\Repository;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Repository\NphOrderRepository;

class NphOrderRepositoryTest extends RepositoryTestCase
{
    private $repo;

    public function setup(): void
    {
        parent::setUp();
        $this->repo = static::$container->get(NphOrderRepository::class);
    }

    public function testGetOrdersByVisitType(): void
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

        $orders = $this->repo->getOrdersByVisitType('P000000001', 'LMT');
        $this->assertSame($nphOrder, $orders[0]);
    }
}
