<?php

namespace App\Tests\Service;

use App\Service\Nph\NphOrderService;
use App\Service\PDFService;
use App\Service\SiteService;
use App\Service\UserService;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;

class PDFServiceTest extends ServiceTestCase
{

    protected $service;
    protected testSetup $testSetup;
    protected NphOrderService $nphOrderService;

    public function setUp(): void
    {
        parent::setUp();
        $this->program = 'nph';
        $this->login('test-nph-user1@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::getContainer()->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::getContainer()->get(PDFService::class);
        $this->testSetup = new testSetup(static::getContainer()->get(EntityManagerInterface::class));
        $this->nphOrderService = static::getContainer()->get(NphOrderService::class);
    }

    public function testBatchPDF(): void
    {
        $pdfService = static::getContainer()->get(PDFService::class);
        $participant = $this->testSetup->generateNphParticipant();
        $nphOrder = $this->testSetup->generateNPHOrder($participant, self::getContainer()->get(UserService::class)->getUserEntity(), self::getContainer()->get(SiteService::class));
        $orderSummary = $this->nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participant->id, $nphOrder->getModule(), $nphOrder->getVisitPeriod());
        $pdf = $pdfService->batchPDF($orderSummary['order'], $participant, $nphOrder->getModule(), $nphOrder->getVisitPeriod());
        $this->assertIsString($pdf);
        $this->assertStringContainsString('%PDF', $pdf);
    }
}
