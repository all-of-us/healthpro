<?php

namespace App\Tests\Service;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Site;
use App\Form\SiteType;
use App\Service\LoggerService;
use App\Service\MayolinkOrderService;
use App\Service\MeasurementService;
use App\Service\OrderService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderServiceTest extends ServiceTestCase
{
    /**
     * @dataProvider siteStatusProvider
     */
    public function testInactiveSiteFormDisabled($status, $isActiveSite, $expectedResult): void
    {
        $mockSiteService = $this->createMock(SiteService::class);
        $mockSiteService->method('isActiveSite')->willReturn($isActiveSite);

        $orderService = new OrderService(
            static::getContainer()->get(RdrApiService::class),
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(MayolinkOrderService::class),
            static::getContainer()->get(UserService::class),
            $mockSiteService,
            static::getContainer()->get(LoggerService::class),
        );

        $orderMock = $this->getMockBuilder(Order::class)
            ->getMock();

        $orderMock->expects($this->any())
            ->method('getStatus')
            ->willReturn($status);
        
        $reflection = new \ReflectionClass($orderService);
        $property = $reflection->getProperty('order');
        $property->setValue($orderService, $orderMock);

        $result = $orderService->inactiveSiteFormDisabled();
        $this->assertSame($expectedResult, $result);
    }

    public function siteStatusProvider(): array
    {
        return [
            'No status, inactive site: expect true' => [null, false, true],
            'Unlock status, inactive site: expect false' => ['unlock', false, false],
            'No status, active site: expect false' => [null, true, false],
            'Unlock status, active site: expect false' => ['unlock', true, false]
        ];
    }
}
