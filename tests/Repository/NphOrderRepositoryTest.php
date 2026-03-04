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
        $this->repo = static::getContainer()->get(NphOrderRepository::class);
        $this->nphOrder = $this->createTestOrder();
    }

    private function createTestOrder(): NphOrder
    {
        $user = $this->getUser();
        $siteId = $this->getSite()->getGoogleGroup();
        $nphOrder = new NphOrder();
        $nphOrder->setModule(1);
        $nphOrder->setVisitPeriod('LMT');
        $nphOrder->setTimepoint('preLMT');
        $nphOrder->setOrderId('100000001');
        $nphOrder->setParticipantId('P000000001');
        $nphOrder->setBiobankId('T0000000001');
        $nphOrder->setUser($user);
        $nphOrder->setSite($siteId);
        $nphOrder->setCreatedTs(new \DateTime());
        $nphOrder->setOrderType('urine');
        $nphOrder->setDowntimeGenerated(false);
        $this->em->persist($nphOrder);
        $this->em->flush();

        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId('100000002');
        $nphSample->setSampleCode('URINES');
        $nphSample->setSampleGroup('100000008');
        $nphSample->setCollectedTs(new \DateTime());
        $this->em->persist($nphSample);
        $this->em->flush();
        $nphSample2 = new NphSample();
        $nphSample2->setNphOrder($nphOrder);
        $nphSample2->setSampleId('100000003');
        $nphSample2->setSampleCode('SST8P5');
        $nphSample2->setSampleGroup('100000008');
        $nphSample2->setCollectedTs(new \DateTime());
        $nphSample2->setFinalizedTs(new \DateTime());
        $this->em->persist($nphSample2);
        $this->em->flush();
        $nphSample3 = new NphSample();
        $nphSample3->setNphOrder($nphOrder);
        $nphSample3->setSampleId('100000004');
        $nphSample3->setSampleCode('NAILS');
        $nphSample3->setSampleGroup('100000008');
        $nphSample3->setCollectedTs(new \DateTime());
        $nphSample3->setFinalizedTs(new \DateTime());
        $nphSample3->setModifiedTs(new \DateTime());
        $nphSample3->setModifyType(NphSample::UNLOCK);
        $this->em->persist($nphSample3);
        $this->em->flush();
        return $nphOrder;
    }


    public function testGetOrdersByVisitType(): void
    {
        $orders = $this->repo->getOrdersByVisitType('P000000001', 'LMT', 1);
        $this->assertSame($this->nphOrder, $orders[0]);
    }

    public function testGetOrdersBySampleGroup(): void
    {
        $orders = $this->repo->getOrdersBySampleGroup('P000000001', '100000008');
        $this->assertSame($this->nphOrder, $orders[0]);
    }

    public function testGetRecentOrdersBySite(): void
    {
        $orders = $this->repo->getRecentOrdersBySite($this->nphOrder->getSite());
        $this->assertSame([$this->nphOrder], $orders);
    }

    public function testGetOrdersByDateRange(): void
    {
        $beginning = new \DateTime();
        $beginning->setTime(0, 0, 0);
        $end = new \DateTime();
        $end->setTime(23, 59, 59);
        $orders = $this->repo->getOrdersByDateRange($this->nphOrder->getSite(), $beginning, $end);
        $sampleCodes = explode(',', $orders[0]['sampleCode']);
        $sampleIds = explode(',', $orders[0]['sampleId']);
        $this->assertSame(array_search('URINES', $sampleCodes), array_search('100000002', $sampleIds));
        $this->assertSame($orders[0]['orderId'], '100000001');
        $this->assertCount(1, $orders);
    }

    public function testGetSampleCollectionStatsByDate(): void
    {
        $beginning = new \DateTime();
        $beginning->setTime(0, 0, 0);
        $end = new \DateTime();
        $end->setTime(23, 59, 59);
        $orderStats = $this->repo->getSampleCollectionStatsByDate($this->nphOrder->getSite(), $beginning, $end);
        $this->assertSame($orderStats[0]['createdCount'], 3);
        $this->assertSame($orderStats[0]['collectedCount'], 3);
        $this->assertSame($orderStats[0]['finalizedCount'], 2);
    }

    public function testGetUnfinalizedSampleCollectionStats(): void
    {
        $orderStats = $this->repo->getUnfinalizedSampleCollectionStats($this->nphOrder->getSite());
        $this->assertSame($orderStats[0]['createdCount'], 1);
        $this->assertSame($orderStats[0]['collectedCount'], 1);
        $this->assertSame($orderStats[0]['finalizedCount'], 0);
    }

    public function testGetUnfinalizedSamples(): void
    {
        $samples = $this->repo->getUnfinalizedSamples($this->nphOrder->getSite());
        $this->assertSame($samples[0]['sampleId'], '100000002');
        $this->assertCount(1, $samples);
    }

    public function testGetRecentlyModifiedSamples(): void
    {
        $modifiedSince = new \DateTime('-1 day');
        $samples = $this->repo->getRecentlyModifiedSamples($this->nphOrder->getSite(), $modifiedSince);
        $this->assertSame($samples[0]['sampleId'], '100000004');
        $this->assertCount(1, $samples);
        $this->assertSame($samples[0]['modifyType'], NphSample::UNLOCK);
    }

    public function testGetTodaysBiobankOrders(): void
    {
        $timezone = 'America/Chicago';
        $startDate = new \DateTime('today', new \DateTimeZone($timezone));
        $endDate = new \DateTime('tomorrow', new \DateTimeZone($timezone));
        $samples = $this->repo->getTodaysBiobankOrders($timezone);
        foreach ($samples as $sample) {
            if ($sample['site'] === $this->nphOrder->getSite()) {
                $this->assertGreaterThanOrEqual($startDate, $sample['createdTs']);
                $this->assertLessThanOrEqual($endDate, $sample['createdTs']);
            }
        }
    }

    public function testGetUnfinalizedBiobankSamples(): void
    {
        $samples = $this->repo->getUnfinalizedBiobankSamples();
        foreach ($samples as $sample) {
            $this->assertSame(null, $sample['finalizedTs']);
        }
    }

    public function testGetUnlockedBiobankSamples(): void
    {
        $samples = $this->repo->getUnlockedBiobankSamples();
        foreach ($samples as $sample) {
            $this->assertSame(NphSample::UNLOCK, $sample['modifyType']);
        }
    }

    public function testGetRecentlyModifiedBiobankSamples(): void
    {
        $timezone = 'America/Chicago';
        $endDate = new \DateTime('-7 days', new \DateTimeZone($timezone));
        $samples = $this->repo->getRecentlyModifiedBiobankSamples($timezone);
        foreach ($samples as $sample) {
            $this->assertNotNull($sample['modifyType']);
            if ($sample['site'] === $this->nphOrder->getSite()) {
                $this->assertGreaterThanOrEqual($endDate, $sample['modifiedTs']);
            }
        }
    }

    public function testGetDowntimeOrders(): void
    {
        $this->nphOrder->setDowntimeGenerated(true);
        $this->em->persist($this->nphOrder);
        $this->em->flush();
        $samples = $this->repo->getDowntimeOrders();
        foreach ($samples as $sample) {
            $this->assertSame(true, $sample['DowntimeGenerated']);
        }
    }
}
