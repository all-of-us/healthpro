<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\User;
use App\Security\User as SecurityUser;
use App\Service\LoggerService;
use App\Service\MayolinkOrderService;
use App\Service\OrderService;
use App\Service\Ppsc\PpscApiService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;

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
            static::getContainer()->get(PpscApiService::class),
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
            static::getContainer()->get(PpscApiService::class),
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

    public function testSetAndGetParticipant(): void
    {
        $orderService = $this->buildOrderService();
        $participant = (object) ['id' => 'P123456789'];
        $orderService->setParticipant($participant);
        $this->assertSame($participant, $orderService->getParticipant());
    }

    public function testGenerateId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $orderService = $this->buildOrderService(['em' => $em]);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{9}$/', $orderService->generateId());
    }

    public function testGenerateIdThrowsWhenNoUniqueId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new Order());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $orderService = $this->buildOrderService(['em' => $em]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to generate unique order id');
        $orderService->generateId();
    }

    /**
     * @dataProvider cancelRestoreRdrObjectProvider
     */
    public function testGetCancelRestoreRdrObject(string $type, string $expectedStatus, string $expectedInfoKey): void
    {
        $userService = $this->createMock(UserService::class);
        $userService->method('getUserEntity')->willReturn($this->createMock(User::class));
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $order = $this->createMock(Order::class);
        $order->method('getOrderUser')->willReturn('user@example.com');
        $order->method('getOrderSite')->willReturn('site-123');
        $order->method('getOrderUserSiteData')->willReturn([
            'author' => ['system' => 'x', 'value' => 'user@example.com'],
            'site' => ['system' => 'y', 'value' => 'site-123'],
        ]);

        $orderService = $this->buildOrderService([
            'userService' => $userService,
            'siteService' => $siteService,
        ]);
        $this->setOrderProperty($orderService, $order);

        $object = $orderService->getCancelRestoreRdrObject($type, 'A reason');
        $this->assertSame($expectedStatus, $object->status);
        $this->assertSame('A reason', $object->amendedReason);
        $this->assertSame('user@example.com', $object->{$expectedInfoKey}['author']['value']);
    }

    public function cancelRestoreRdrObjectProvider(): array
    {
        return [
            'Cancel' => [Order::ORDER_CANCEL, 'cancelled', 'cancelledInfo'],
            'Restore' => [Order::ORDER_RESTORE, 'restored', 'restoredInfo'],
        ];
    }

    public function testGetNewProcessedSamples(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getProcessedSamplesTs')->willReturn('{"1SS08":1606753560,"1PS08":1606753560}');

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);

        $result = $orderService->getNewProcessedSamples(['1SS08']);
        $this->assertSame(['1SS08'], json_decode($result['samples'], true));
        $this->assertSame(['1SS08' => 1606753560], json_decode($result['timeStamps'], true));
    }

    public function testGetNewFinalizedSamplesCollected(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getFinalizedSamples')->willReturn('["1SS08","1PS08"]');

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);

        $result = $orderService->getNewFinalizedSamples('collected', ['1SS08']);
        $this->assertSame(['1SS08'], json_decode($result, true));
    }

    public function testGetNewFinalizedSamplesProcessed(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getFinalizedSamples')->willReturn('["1SS08","1PS08"]');

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);

        // 1PS08 requires processing but is not in the processed samples list, so it is removed
        $result = $orderService->getNewFinalizedSamples('processed', ['1SS08']);
        $this->assertSame(['1SS08'], json_decode($result, true));
    }

    /**
     * @dataProvider canEditProvider
     */
    public function testCanEdit(bool $status, bool $editExistingOnly, $orderId, bool $expected): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['status' => $status, 'editExistingOnly' => $editExistingOnly]);

        $this->assertSame($expected, $orderService->canEdit());
    }

    public function canEditProvider(): array
    {
        return [
            'Active participant' => [true, false, 5, true],
            'Inactive participant, no order' => [false, true, null, false],
            'Inactive participant with existing order, edit allowed' => [false, true, 5, true],
            'Inactive participant with existing order, edit not allowed' => [false, false, 5, false],
        ];
    }

    public function testGetCustomSamplesInfo(): void
    {
        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getTimezone')->willReturn('America/Chicago');
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);

        $order = $this->createOrder($this->getOrderData());
        $orderService = $this->buildOrderService(['userService' => $userService]);
        $orderService->loadSamplesSchema($order);

        $samples = $orderService->getCustomSamplesInfo();
        $this->assertNotEmpty($samples);
        $this->assertArrayHasKey('code', $samples[0]);
        $this->assertArrayHasKey('sampleId', $samples[0]);
    }

    public function testSetOrderUpdateFromFormFinalized(): void
    {
        $userEntity = $this->createMock(User::class);
        $userEntity->method('getTimezoneId')->willReturn(1);
        $userService = $this->createMock(UserService::class);
        $userService->method('getUserEntity')->willReturn($userEntity);

        $form = $this->createMock(FormInterface::class);
        $finalizedTs = new \DateTime('2021-01-01 09:00:00');
        $form->method('getData')->willReturn([
            'finalizedNotes' => 'A note',
            'finalizedTs' => $finalizedTs,
            'finalizedSamples' => ['1SS08'],
            'fedexTracking' => 'TRACK1',
        ]);
        $form->method('has')->willReturnCallback(fn ($field) => $field === 'finalizedSamples');

        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('setFinalizedNotes')->with('A note');
        $order->expects($this->once())->method('setFinalizedTs')->with($finalizedTs);
        $order->expects($this->once())->method('setFinalizedTimezoneId')->with(1);
        $order->expects($this->once())->method('setFinalizedSamples')->with(json_encode(['1SS08']));
        $order->expects($this->once())->method('setSubmissionTs');
        $order->expects($this->once())->method('setFedexTracking')->with('TRACK1');

        $orderService = $this->buildOrderService(['userService' => $userService]);
        $this->setOrderProperty($orderService, $order);

        $orderService->setOrderUpdateFromForm('finalized', $form);
    }

    public function testGetOrderFormDataFinalized(): void
    {
        $finalizedTs = new \DateTime('2021-01-01 09:00:00');
        $order = $this->createMock(Order::class);
        $order->method('getFinalizedNotes')->willReturn('A note');
        $order->method('getFinalizedTs')->willReturn($finalizedTs);
        $order->method('getFinalizedSamples')->willReturn('["1SS08"]');
        $order->method('getFedexTracking')->willReturn('TRACK1');

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);

        $formData = $orderService->getOrderFormData('finalized');
        $this->assertSame('A note', $formData['finalizedNotes']);
        $this->assertSame($finalizedTs, $formData['finalizedTs']);
        $this->assertSame(['1SS08'], $formData['finalizedSamples']);
        $this->assertSame('TRACK1', $formData['fedexTracking']);
    }

    public function testLoadFromJsonObject(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getSamplesInformation')->willReturn([]);
        $order->expects($this->once())->method('setParticipantId')->with('P123456789');
        $order->expects($this->once())->method('setType')->with('kit');

        $orderService = $this->buildOrderService();
        $this->setOrderProperty($orderService, $order);

        $object = $this->getRdrOrderObject();
        $result = $orderService->loadFromJsonObject($object);
        $this->assertSame($order, $result);
    }

    public function testCreateOrderHistorySuccess(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollback');

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('find')->willReturn($this->createMock(User::class));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getRepository')->willReturn($userRepository);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getId')->willReturn(1);
        $userEntity = $this->createMock(User::class);
        $userEntity->method('getTimezoneId')->willReturn(1);
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);
        $userService->method('getUserEntity')->willReturn($userEntity);
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(10);
        $order->method('getVersion')->willReturn('3.1');

        $orderService = $this->buildOrderService([
            'em' => $em,
            'userService' => $userService,
            'siteService' => $siteService,
            'loggerService' => $this->createMock(LoggerService::class),
        ]);
        $this->setOrderProperty($orderService, $order);

        $this->assertTrue($orderService->createOrderHistory(Order::ORDER_EDIT, 'A reason'));
    }

    public function testRevertOrderReturnsFalseWhenOrderNotFound(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(null);

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertFalse($orderService->revertOrder());
    }

    /**
     * @dataProvider cancelRestoreOrderProvider
     */
    public function testCancelRestoreOrder(int $statusCode, bool $expected): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willReturn(new Response($statusCode, [], '{}'));

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertSame($expected, $orderService->cancelRestoreOrder(new \stdClass()));
    }

    public function cancelRestoreOrderProvider(): array
    {
        return [
            'Success' => [200, true],
            'Non-200 response' => [400, false],
        ];
    }

    public function testCancelRestoreOrderException(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willThrowException(new \Exception('API error'));
        $ppscApiService->expects($this->once())->method('logException');

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertFalse($orderService->cancelRestoreOrder(new \stdClass()));
    }

    public function testCancelRestoreRdrOrder(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willReturn(new Response(200, [], '{}'));
        $userService = $this->createMock(UserService::class);
        $userService->method('getUserEntity')->willReturn($this->createMock(User::class));
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');
        $order->method('getOrderUser')->willReturn('user@example.com');
        $order->method('getOrderSite')->willReturn('site-123');
        $order->method('getOrderUserSiteData')->willReturn(['author' => [], 'site' => []]);

        $orderService = $this->buildOrderService([
            'ppscApiService' => $ppscApiService,
            'userService' => $userService,
            'siteService' => $siteService,
        ]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertTrue($orderService->cancelRestoreRdrOrder(Order::ORDER_CANCEL, 'A reason'));
    }

    public function testCreateOrderSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{"healthProOrderId":"HPO123"}'));
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertSame('HPO123', $orderService->createOrder('P1', new \stdClass()));
    }

    public function testCreateOrderMissingId(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{}'));
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertFalse($orderService->createOrder('P1', new \stdClass()));
    }

    public function testCreateOrderException(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willThrowException(new \Exception('API error'));
        $ppscApiService->expects($this->once())->method('logException');
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertFalse($orderService->createOrder('P1', new \stdClass()));
    }

    /**
     * @dataProvider editOrderProvider
     */
    public function testEditOrder(int $statusCode, bool $expected): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('put')->willReturn(new Response($statusCode, [], '{}'));

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertSame($expected, $orderService->editOrder(new \stdClass()));
    }

    public function editOrderProvider(): array
    {
        return [
            'Success' => [200, true],
            'Non-200 response' => [400, false],
        ];
    }

    public function testGetOrdersByParticipant(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(new Response(200, [], '{"data":[{"id":"O1"}]}'));
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $result = $orderService->getOrdersByParticipant('P1');
        $this->assertCount(1, $result);
        $this->assertSame('O1', $result[0]->id);
    }

    public function testGetOrdersByParticipantNullResponse(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(null);
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertSame([], $orderService->getOrdersByParticipant('P1'));
    }

    public function testGetOrders(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(new Response(200, [], '{"data":[{"id":"O1"},{"id":"O2"}]}'));
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertCount(2, $orderService->getOrders(['param' => 'value']));
    }

    public function testGetOrdersNullResponse(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(null);
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertSame([], $orderService->getOrders());
    }

    public function testGetOrderSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(new Response(200, [], '{"id":"O1"}'));
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $result = $orderService->getOrder('P1', 'O1');
        $this->assertIsObject($result);
        $this->assertSame('O1', $result->id);
    }

    public function testGetOrderNullResponse(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(null);
        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->assertFalse($orderService->getOrder('P1', 'O1'));
    }

    public function testGetLabelsPdfMockOrder(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->willReturnCallback(fn ($field) => $field === 'ml_mock_order');
        $orderService = $this->buildOrderService(['params' => $params]);
        $this->assertSame(['status' => 'success'], $orderService->getLabelsPdf());
    }

    public function testGetLabelsPdfMissingMayoAccount(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->willReturn(false);

        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getTimezone')->willReturn('America/Chicago');
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $order = $this->createMock(Order::class);
        $order->method('getCreatedTs')->willReturn(new \DateTime('2021-01-01'));

        $orderService = $this->buildOrderService([
            'params' => $params,
            'userService' => $userService,
            'em' => $em,
            'siteService' => $siteService,
        ]);
        $this->setOrderProperty($orderService, $order);

        $result = $orderService->getLabelsPdf();
        $this->assertSame('fail', $result['status']);
        $this->assertArrayHasKey('errorMessage', $result);
    }

    public function testSendOrderToMayoMockOrder(): void
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('has')->willReturnCallback(fn ($field) => $field === 'ml_mock_order');
        $params->method('get')->with('ml_mock_order')->willReturn('MOCK1');
        $orderService = $this->buildOrderService(['params' => $params]);
        $this->assertSame(['status' => 'success', 'mayoId' => 'MOCK1'], $orderService->sendOrderToMayo('client-1'));
    }

    public function testSendToRdrCreateSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{"healthProOrderId":"HPO123"}'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn(null);
        $order->method('getRdrObject')->willReturn(new \stdClass());
        $order->method('getParticipantId')->willReturn('P1');
        $order->expects($this->once())->method('setRdrId')->with('HPO123');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService, 'em' => $em]);
        $this->setOrderProperty($orderService, $order);

        $this->assertTrue($orderService->sendToRdr());
    }

    public function testCreateRdrOrderFailure(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{}'));
        $ppscApiService->method('getLastErrorCode')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $order = $this->createMock(Order::class);
        $order->method('getRdrObject')->willReturn(new \stdClass());
        $order->method('getParticipantId')->willReturn('P1');
        $order->expects($this->never())->method('setRdrId');

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService, 'em' => $em]);
        $this->setOrderProperty($orderService, $order);

        $this->assertFalse($orderService->createRdrOrder());
    }

    public function testEditRdrOrderFailure(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('put')->willReturn(new Response(400, [], '{}'));

        $order = $this->createMock(Order::class);
        $order->method('getRdrId')->willReturn('R1');
        $order->method('getEditRdrObject')->willReturn(new \stdClass());

        $orderService = $this->buildOrderService(['ppscApiService' => $ppscApiService]);
        $this->setOrderProperty($orderService, $order);
        $orderService->setParticipant((object) ['id' => 'P123456789']);

        $this->assertFalse($orderService->editRdrOrder());
    }

    public function testGetRequisitionPdf(): void
    {
        $mayolinkOrderService = $this->createMock(MayolinkOrderService::class);
        $mayolinkOrderService->method('getRequisitionPdf')->willReturn('pdf-content');

        $order = $this->createMock(Order::class);
        $order->method('getMayoId')->willReturn('M1');

        $orderService = $this->buildOrderService(['mayolinkOrderService' => $mayolinkOrderService]);
        $this->setOrderProperty($orderService, $order);

        $this->assertSame('pdf-content', $orderService->getRequisitionPdf());
    }

    public function testLoadSamplesSchema(): void
    {
        $order = $this->createOrder($this->getOrderData());
        $orderService = $this->buildOrderService();
        $orderService->loadSamplesSchema($order);
        $this->assertNotEmpty($order->getSamplesInformation());
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
        $order = new Order();
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function buildOrderService(array $overrides = []): OrderService
    {
        return new OrderService(
            $overrides['ppscApiService'] ?? static::getContainer()->get(PpscApiService::class),
            $overrides['params'] ?? static::getContainer()->get(ParameterBagInterface::class),
            $overrides['em'] ?? static::getContainer()->get(EntityManagerInterface::class),
            $overrides['mayolinkOrderService'] ?? $this->createMock(MayolinkOrderService::class),
            $overrides['userService'] ?? static::getContainer()->get(UserService::class),
            $overrides['siteService'] ?? static::getContainer()->get(SiteService::class),
            $overrides['loggerService'] ?? static::getContainer()->get(LoggerService::class),
        );
    }

    private function setOrderProperty(OrderService $orderService, $order): void
    {
        $reflection = new \ReflectionClass($orderService);
        $property = $reflection->getProperty('order');
        $property->setValue($orderService, $order);
    }

    private function getRdrOrderObject(): \stdClass
    {
        $object = new \stdClass();
        $object->id = 'R1';
        $object->subject = 'Patient/P123456789';
        $object->origin = 'example-origin';
        $object->created = '2021-01-01T08:00:00Z';
        $object->samples = [
            (object) [
                'test' => '1SS08',
                'collected' => '2021-01-01T08:00:00Z',
                'processed' => '2021-01-01T08:30:00Z',
                'finalized' => '2021-01-01T09:00:00Z',
            ],
        ];
        $object->notes = (object) [
            'collected' => 'collected note',
            'processed' => 'processed note',
            'finalized' => 'finalized note',
        ];
        $object->identifier = [
            (object) ['system' => 'https://example.org/tracking-number', 'value' => 'TRACK1'],
            (object) ['system' => 'https://example.org/kit-id', 'value' => 'KIT1'],
        ];
        $object->collectedInfo = null;
        $object->processedInfo = null;
        $object->finalizedInfo = null;
        return $object;
    }
}
