<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerification;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class BiobankNightlyReportService
{
    public const BUCKET_NAME_STABLE = 'healthpro-stable-biobank-nightly-report';
    public const BUCKET_NAME_PROD = 'healthpro-prod-biobank-nightly-report';

    protected EntityManagerInterface $em;
    protected GcsBucketService $gcsBucketService;
    protected EnvironmentService $env;

    public function __construct(
        EntityManagerInterface $em,
        GcsBucketService $gcsBucketService,
        EnvironmentService $env
    ) {
        $this->em = $em;
        $this->gcsBucketService = $gcsBucketService;
        $this->env = $env;

    }

    public function generateNightlyReport(): void
    {
        $nightlyReportCsvData = $this->getNightlyReportCsvData();

        // Create a temporary stream to hold the CSV data
        $tempStream = fopen('php://temp', 'w');
        foreach ($nightlyReportCsvData as $row) {
            fputcsv($tempStream, $row);
        }

        // Upload the CSV data to the bucket
        $fileName = 'nightly-report-' . date('Ymd-His') . '.csv';
        $bucketName = $this->env->isProd() ? self::BUCKET_NAME_PROD : self::BUCKET_NAME_STABLE;
        $this->gcsBucketService->uploadFile($bucketName, $tempStream, $fileName);
    }

    private function getNightlyReportCsvData(): array
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
        return $csvData;
    }
}
