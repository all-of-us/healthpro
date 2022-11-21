<?php

namespace App\Tests\Service;

use App\Helper\Participant;
use App\Service\PDFService;
use App\Tests\testSetup;

class PDFServiceTest extends ServiceTestCase
{

    protected function getOrderData(): array
    {
        return [
            'minus15min' => [
                'SST8P5' => [
                    'SampleID' => '9292338307',
                    'SampleName' => '8.5 mL SST',
                    'OrderID' => '6505378640',
                    'SampleCollectionVolume' => '8.5 mL'
                ]
            ]
        ];
    }

    public function testBatchPDF(): void
    {
        $module = '1';
        $visit = 'LMT';
        $pdfService = static::getContainer()->get(PDFService::class);
        $participant = testSetup::generateParticipant('P000001', 'John', 'Doe', new \DateTime('2000-01-01'));
        $orderSummary = $this->getOrderData();
        $pdf = $pdfService->batchPDF($orderSummary, $participant, $module, $visit);
        $this->assertIsString($pdf);
        $this->assertStringContainsString('%PDF', $pdf);
    }
}
