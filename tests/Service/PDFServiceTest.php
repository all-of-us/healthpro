<?php

namespace App\Tests\Service;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Service\Nph\NphOrderService;
use App\Service\PDFService;
use App\Service\SiteService;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;

class PDFServiceTest extends ServiceTestCase
{

    protected $service;
    protected $testSetup;
    protected NphOrderService $nphOrderService;

    public function setUp(): void
    {
        $this->service = static::getContainer()->get(PDFService::class);
        $this->testSetup = new testSetup(static::getContainer()->get(EntityManagerInterface::class));
        $this->nphOrderService = static::getContainer()->get(NphOrderService::class);
    }

    public function testBatchPDF(): void
    {
        $pdfService = static::getContainer()->get(PDFService::class);
        $participant = $this->testSetup->generateParticipant();
        $nphOrder = $this->testSetup->generateNphOrder($participant);
        $orderSummary = $this->nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participant->id, $nphOrder->getModule(), $nphOrder->getVisitType());
        $pdf = $pdfService->batchPDF($orderSummary['order'], $participant, $nphOrder->getModule(), $nphOrder->getVisitType());
        $this->assertIsString($pdf);
        $this->assertStringContainsString('%PDF', $pdf);
    }
}
