<?php

namespace App\Tests\Service;

use App\Entity\Incentive;
use App\Service\IncentiveService;
use App\Service\SiteService;

class IncentiveServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(IncentiveService::class);
    }

    public function testRdrObject(): void
    {
        $incentive = $this->createIncentive();
        $rdrObject = $this->service->getRdrObject($incentive);
        self::assertEquals('test@example.com', $rdrObject->createdBy);
        self::assertEquals('hpo-site-test', $rdrObject->site);
        self::assertEquals('redraw', $rdrObject->occurrence);
        self::assertEquals(new \DateTime('2022-03-22'), $rdrObject->dateGiven);
        self::assertEquals('redraw', $rdrObject->occurrence);
        self::assertEquals('gift_card', $rdrObject->incentiveType);
        self::assertEquals('target', $rdrObject->giftcardType);
        self::assertEquals(15, $rdrObject->amount);

        $incentive = $this->createIncentive(Incentive::AMEND);
        $rdrObject = $this->service->getRdrObject($incentive, Incentive::AMEND);
        self::assertEquals(1, $rdrObject->incentiveId);

        $incentive = $this->createIncentive(Incentive::CANCEL);
        $rdrObject = $this->service->getRdrObject($incentive, Incentive::CANCEL);
        self::assertEquals('test@example.com', $rdrObject->cancelledBy);
        self::assertEquals(true, $rdrObject->cancel);
        self::assertEquals(1, $rdrObject->incentiveId);
    }

    private function createIncentive($type = Incentive::CREATE)
    {
        $incentive = new Incentive();
        $incentive
            ->setCreatedTs(new \DateTime())
            ->setIncentiveDateGiven(new \DateTime('2022-03-22'))
            ->setOtherIncentiveOccurrence('redraw')
            ->setOtherIncentiveType('gift_card')
            ->setGiftCardType('target')
            ->setIncentiveAmount(15);
        if ($type !== Incentive::CREATE) {
            $incentive->setRdrId(1);
        }
        return $incentive;
    }
}
