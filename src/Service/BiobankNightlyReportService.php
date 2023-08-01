<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerification;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class BiobankNightlyReportService
{
    private const BUCKET_NAME = 'aou-biobank-nightly-report';

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
            $collectedTs = $order['collectedTs'];
            $collectedTs->setTimezone(new \DateTimeZone('America/Chicago'));
            $finalizedTs = $order['finalizedTs'];
            $finalizedTs->setTimezone(new \DateTimeZone('America/Chicago'));
            $csvData[] = [$order['biobankId'], $order['orderId'], $order['rdrId'], $order['mayolinkAccount'],
                $collectedTs->format('Y-m-d H:i:s'), $finalizedTs->format('Y-m-d H:i:s')];
        }

        // Create a temporary stream to hold the CSV data
        $tempStream = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($tempStream, $row);
        }

        // Upload the CSV data to the bucket
        $fileName = 'nightly-report-' . date('Ymd-His') . '.csv';
        $this->gcsBucketService->uploadFile(self::BUCKET_NAME, $tempStream, $fileName);
    }
}
