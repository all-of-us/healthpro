<?php

namespace App\Tests\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;

class OrderRepositoryTest extends RepositoryTestCase
{
    private OrderRepository $repo;

    public function setup(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(OrderRepository::class);
    }

    protected function getUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    protected function createOrder($params = []): Order
    {
        $order = new Order;
        foreach ($params as $key => $value) {
            $order->{'set' . ucfirst($key)}($value);
        }
        $this->em->persist($order);
        $this->em->flush();
        return $order;
    }

    /**
     * @dataProvider nightlyReportOrdersDataProvider
     */
    public function testGetNightlyReportOrders(array $ordersInputData, array $expectedResult)
    {
        foreach ($ordersInputData as $data) {
            $data = array_merge($data, $this->getRequiredOrderData());
            $data['participantId'] = 'P100001';
            $this->createOrder($data);
        }
        $nightlyReportsData = $this->repo->getNightlyReportOrders();
        $this->assertSame($nightlyReportsData[0]['biobankId'], $expectedResult[0]['biobankId']);
        $this->assertSame($nightlyReportsData[0]['orderId'], $expectedResult[0]['orderId']);
        $this->assertSame($nightlyReportsData[0]['rdrId'], $expectedResult[0]['rdrId']);
        $this->assertSame($nightlyReportsData[0]['collectedTs']->format('Y-m-d H:i:s T'), $expectedResult[0]['collectedTs']->format('Y-m-d H:i:s T'));
        $this->assertSame($nightlyReportsData[0]['finalizedTs']->format('Y-m-d H:i:s T'), $expectedResult[0]['finalizedTs']->format('Y-m-d H:i:s T'));
    }

    public function nightlyReportOrdersDataProvider(): array
    {
        // Define different scenarios and their expected outputs
        $now = new \DateTime('now');
        $oneHourBefore = new \DateTime('-1 hour');
        $twoDaysBefore = new \DateTime('-2 day');
        return [
            'valid_orders' => [
                'inputData' => [
                    [
                        'biobankId' => 'T1001',
                        'orderId' => '10001',
                        'rdrId' => 'WEB1001',
                        'collectedTs' => $now,
                        'finalizedTs' => $oneHourBefore
                    ],
                    [
                        'biobankId' => 'T1002',
                        'orderId' => '10002',
                        'rdrId' => 'WEB1002',
                        'collectedTs' => $now,
                        'finalizedTs' => $twoDaysBefore
                    ]
                ],
                'expectedResult' => [
                    [
                        'biobankId' => 'T1001',
                        'orderId' => '10001',
                        'rdrId' => 'WEB1001',
                        'collectedTs' => $now,
                        'finalizedTs' => $oneHourBefore,
                        'mayolinkAccount' => null
                    ]
                ],
            ]
        ];
    }

    private function getRequiredOrderData(): array
    {
        return [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
            'participantId' => 'P123456789'
        ];
    }
}
