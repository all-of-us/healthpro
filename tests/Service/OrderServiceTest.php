<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\User;
use App\Service\LoggerService;
use App\Service\MayolinkOrderService;
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
            $this->createMock(MayolinkOrderService::class),
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

    public function testUpdateOrderTubes(): void
    {
        $orderService = new OrderService(
            static::getContainer()->get(RdrApiService::class),
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(EntityManagerInterface::class),
            $this->createMock(MayolinkOrderService::class),
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(SiteService::class),
            static::getContainer()->get(LoggerService::class),
        );
        $formInterface = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $orderData = $this->getOrderData();
        $order = $this->createOrder($orderData);
        $this->assertTrue(in_array('1PS08', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('PS04A', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('PS04B', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('PS04A', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('PS04B', json_decode($order->getProcessedSamples())));
        $this->assertTrue(in_array('1PS08', json_decode($order->getProcessedSamples())));
        $order = $orderService->updateOrderVersion($order, '3.2', $formInterface);
        $this->assertFalse(in_array('1PS08', json_decode($order->getProcessedSamples())));
        $this->assertTrue(in_array('PS04A', json_decode($order->getProcessedSamples())));
        $this->assertTrue(in_array('PS04B', json_decode($order->getProcessedSamples())));
        $this->assertTrue(in_array('PS04A', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('PSO4B', json_decode($order->getProcessedSamples())));
        $this->assertFalse(in_array('1PS08', json_decode($order->getProcessedSamples())));
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

    protected function getUser()
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        return $user;
    }

    protected function createOrder($params = [])
    {
        $order = new Order;
        foreach ($params as $key => $value) {
            $order->{'set' . ucfirst($key)}($value);
        }
        return $order;
    }

    protected function getOrderData(string $version = '3.1'): array
    {
        $collectedSamples = '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]';
        $processedSamples = '["1SS08","1PS08"]';
        $processedSamplesTs = '{"1SS08":1606753560,"1PS08":1606753560}';
        $finalizedSamples = '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]';
        if ($version === '3.2') {
            $collectedSamples = '["1SS08","PS04A","PS04B","1HEP4","PS04A","1ED10","1CFD9","1PXR2","1UR10"]';
            $processedSamples = '["1SS08","PS04A","PS04B",]';
            $processedSamplesTs = '{"1SS08":1606753560,"PS04A":1606753560,"PS04B":1606753560}';
            $finalizedSamples = '["1SS08","PS04A","PS04B","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]';
        }
        return [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
            'participantId' => 'P123456789',
            'rdrId' => 'WEB123456789',
            'biobankId' => 'Y123456789',
            'orderId' => '0123456789',
            'mayoId' => 'WEB123456789',
            'collectedSamples' => $collectedSamples,
            'processedSamples' => $processedSamples,
            'processedSamplesTs' => $processedSamplesTs,
            'processedCentrifugeType' => 'swinging_bucket',
            'finalizedSamples' => $finalizedSamples,
            'version' => $version
        ];
    }
}
