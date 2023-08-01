<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerification;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class BiobankNightlyReportService
{
    protected EntityManagerInterface $em;
    protected GcsBucketService $gcsBucketService;

    public function __construct(
        EntityManagerInterface $em,
        GcsBucketService $gcsBucketService
    ) {
        $this->em = $em;
        $this->gcsBucketService = $gcsBucketService;

    }

    public function generateNightlyReport(): void
    {
        $orders = $this->em->getRepository(Order::class)->getNightlyReportOrders();
        $csvData = [];
        $csvData[] = ['BiobankID', 'Order Number', 'WEB Order Number', 'ML #', 'Collection DateTime', 'Finalization DateTime'];
        foreach ($orders as $order) {
            $csvData[] = [$order['biobankId'], $order['orderId'], $order['rdrId'], $order['mayolinkAccount'],
                $order['collectedTs']->format('Y-m-d H:i:s'), $order['finalizedTs']->format('Y-m-d H:i:s')];
        }

        // Create a temporary stream to hold the CSV data
        $tempStream = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($tempStream, $row);
        }

        // Upload the CSV data to the bucket
        $fileName = 'nightly-report-' . date('Ymd-His') . '.csv';
        $this->gcsBucketService->uploadFile('aou-biobank-nightly-report', $tempStream, $fileName);
    }
}
