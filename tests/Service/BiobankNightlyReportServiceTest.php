<?php

namespace App\Tests\Service;

use App\Repository\OrderRepository;
use App\Service\BiobankNightlyReportService;
use App\Service\EnvironmentService;
use App\Service\GcsBucketService;
use Doctrine\ORM\EntityManagerInterface;

class BiobankNightlyReportServiceTest extends ServiceTestCase
{
    /**
     * @dataProvider generateNightlyReportsDataProvider
     */

    public function testGenerateNightlyReport(bool $isProd, string $expectedBucketName)
    {
        // Mock the Order entity repository
        $orderRepositoryMock = $this->getMockBuilder(OrderRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure the getNightlyReportOrders method on the repository to return dummy data
        $orderRepositoryMock->expects($this->any())
            ->method('getNightlyReportOrders')
            ->willReturn([]);

        // Mock dependencies
        $emMock = $this->createMock(EntityManagerInterface::class);
        $gcsBucketServiceMock = $this->createMock(GcsBucketService::class);
        $envMock = $this->createMock(EnvironmentService::class);

        // Configure the EntityManagerInterface mock to return the Order repository mock
        $emMock->expects($this->any())
            ->method('getRepository')
            ->willReturn($orderRepositoryMock);

        // Configure the GcsBucketService mock to return true for uploadFile method
        $gcsBucketServiceMock->method('uploadFile')->willReturn(true);

        // Configure the EnvironmentService mock to return the provided environment (prod or not)
        $envMock->method('isProd')->willReturn($isProd);

        // Create the BiobankNightlyReportService instance with mocked dependencies
        $service = new BiobankNightlyReportService($emMock, $gcsBucketServiceMock, $envMock);

        $expectedFileName = 'nightly-report-' . date('Ymd-His') . '.csv';

        // Assert that the GcsBucketService's uploadFile method is called with correct parameters
        $gcsBucketServiceMock->expects($this->once())
            ->method('uploadFile')
            ->with($expectedBucketName, $this->isType('resource'), $expectedFileName);

        $service->generateNightlyReport();
    }

    public function generateNightlyReportsDataProvider(): array
    {
        return [
            'Staging environment' =>
                [
                    false,
                    BiobankNightlyReportService::BUCKET_NAME_STABLE,
                ],
            'Production environment' =>
                [
                    true,
                    BiobankNightlyReportService::BUCKET_NAME_PROD,
                ],
        ];
    }
}
