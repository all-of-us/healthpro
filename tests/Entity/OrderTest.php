<?php

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    protected function getUser()
    {
        $user = new User;
        $user->setEmail('test@example.com');
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

    protected function createOrderHistory($params = [])
    {
        $order = new OrderHistory();
        foreach ($params as $key => $value) {
            $order->{'set' . ucfirst($key)}($value);
        }
        return $order;
    }

    protected function getOrderData()
    {
        return [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
            'participantId' => 'P123456789',
            'rdrId' => 'WEB123456789',
            'biobankId' => 'Y123456789',
            'orderId' => '0123456789',
            'mayoId' => 'WEB123456789',
            'collectedSamples' => '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]',
            'processedSamples' => '["1SS08","1PS08"]',
            'processedSamplesTs' => '{"1SS08":1606753560,"1PS08":1606753560}',
            'processedCentrifugeType' => 'swinging_bucket',
            'finalizedSamples' => '["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]',
            'version' => '3.1'
        ];
    }

    public function testOrderStep()
    {
        $orderData = $this->getOrderData();
        $order = $this->createOrder($orderData);
        $this->assertSame('print_labels', $order->getCurrentStep());

        $order->setCreatedTs(new \DateTime('2021-01-01 08:00:00'));
        $order->setPrintedTs(new \DateTime('2021-01-01 09:00:00'));
        $this->assertSame('collect', $order->getCurrentStep());

        $order->setCollectedTs(new \DateTime('2021-01-01 10:00:00'));
        $order->setMayoId('WEB123456789');
        $this->assertSame('process', $order->getCurrentStep());

        $order->setProcessedTs(new \DateTime('2021-01-01 11:00:00'));
        $this->assertSame('finalize', $order->getCurrentStep());

        $order->setFinalizedTs(new \DateTime('2021-01-01 12:00:00'));
        $this->assertSame('finalize', $order->getCurrentStep());

        $order = $this->createOrder([
            'createdTs' => new \DateTime('2016-01-01 08:00:00'),
        ]);
        $orderHistory = $this->createOrderHistory(['type' => 'cancel']);
        $order->setHistory($orderHistory);
        $this->assertSame('collect', $order->getCurrentStep());
    }

    public function testSalivaOrderAvailableSteps()
    {
        $orderData = $this->getOrderData();
        $orderData['type'] = 'saliva';
        $order = $this->createOrder($orderData);
        $order->setCreatedTs(new \DateTime('2022-01-01 08:00:00'));
        $order->setPrintedTs(new \DateTime('2022-01-01 09:00:00'));
        $order->setCollectedTs(new \DateTime('2022-01-01 10:00:00'));
        self::assertContains('finalize', $order->getAvailableSteps());
        // Process step should not be present
        self::assertNotContains('process', $order->getAvailableSteps());
    }

    public function testSalivaOrderSteps()
    {
        $orderData = $this->getOrderData();
        $orderData['type'] = 'saliva';
        $order = $this->createOrder($orderData);
        self::assertSame('print_labels', $order->getCurrentStep());

        $order->setCreatedTs(new \DateTime('2022-01-01 08:00:00'));
        $order->setPrintedTs(new \DateTime('2022-01-01 09:00:00'));
        self::assertSame('collect', $order->getCurrentStep());

        //Next step after collect is finalize
        $order->setCollectedTs(new \DateTime('2022-01-01 10:00:00'));
        self::assertSame('finalize', $order->getCurrentStep());

        $order->setFinalizedTs(new \DateTime('2022-01-01 12:00:00'));
        self::assertSame('finalize', $order->getCurrentStep());

        $order = $this->createOrder([
            'createdTs' => new \DateTime('2022-01-01 08:00:00'),
        ]);
        $orderHistory = $this->createOrderHistory(['type' => 'cancel']);
        $order->setHistory($orderHistory);
        self::assertSame('collect', $order->getCurrentStep());
    }

    public function testRdrObject()
    {
        $orderData = $this->getOrderData();
        // HPO order
        $order = $this->createOrder($orderData);
        $order->loadSamplesSchema();
        $this->assertEquals('Patient/P123456789', $order->getRdrObject()->subject);
        // Assert createdInfo
        $this->assertEquals('test@example.com', $order->getRdrObject()->createdInfo['author']['value']);
        $this->assertEquals('hpo-site-test', $order->getRdrObject()->createdInfo['site']['value']);
        // Assert identifiers orderId and mayoId
        $this->assertEquals('0123456789', $order->getRdrObject()->identifier[0]['value']);
        $this->assertEquals('WEB123456789', $order->getRdrObject()->identifier[1]['value']);
        // Assert processed sample codes (swinging_bucket)
        $this->assertEquals('1SST8', $order->getRdrObject()->samples[0]['test']);
        $this->assertEquals('1PST8', $order->getRdrObject()->samples[1]['test']);
        // Assert processingRequired
        $this->assertEquals('1', $order->getRdrObject()->samples[0]['processingRequired']);
        $this->assertEquals('1', $order->getRdrObject()->samples[1]['processingRequired']);

        // DV order
        $orderData['type'] = 'kit';
        $orderData['processedCentrifugeType'] = 'fixed_angle';
        $order = $this->createOrder($orderData);
        $order->loadSamplesSchema();
        // Assert processed sample codes (fixed_angle)
        $this->assertEquals('2SST8', $order->getRdrObject()->samples[0]['test']);
        $this->assertEquals('2PST8', $order->getRdrObject()->samples[1]['test']);
    }

    public function testEditRdrObject()
    {
        $orderHistory = $this->createOrderHistory([
            'user' => $this->getUser(),
            'site' => 'test',
            'reason' => 'Test reason'
        ]);
        $orderData = $this->getOrderData();
        $order = $this->createOrder($orderData);
        $order->setHistory($orderHistory);
        $order->loadSamplesSchema();
        $amendedInfo = [
            'author' => [
                'system' => 'https://www.pmi-ops.org/healthpro-username',
                'value' => $this->getUser()->getEmail()
            ],
            'site' => [
                'system' => 'https://www.pmi-ops.org/site-id',
                'value' => 'hpo-site-test'
            ]
        ];
        $this->assertEquals('Test reason', $order->getEditRdrObject()->amendedReason);
        $this->assertEquals($amendedInfo, $order->getEditRdrObject()->amendedInfo);
    }

    public function testHpoOrdersSampleIds()
    {
        $data = [
            'swinging_bucket' => [
                '1' => [
                    'sampleIds' => [
                        '1SST8' => '1SST8',
                        '1PST8' => '1PST8'
                    ]
                ],
                '2' => [
                    'sampleIds' => [
                        '1SS08' => '1SS08',
                        '1PS08' => '1PS08'
                    ]
                ],
                '3' => [
                    'sampleIds' => [
                        '1SS08' => '1SS08',
                        '1PS08' => '1PS08'
                    ]
                ],
                '3.1' => [
                    'sampleIds' => [
                        '1SS08' => '1SST8',
                        '1PS08' => '1PST8'
                    ]
                ],
                '4' => [
                    'sampleIds' => [
                        '1SS08' => '1SST8',
                        '1PS08' => '1PST8'
                    ]
                ]
            ],
            'fixed_angle' => [
                '1' => [
                    'sampleIds' => [
                        '1SST8' => '1SST8',
                        '1PST8' => '1PST8'
                    ]
                ],
                '2' => [
                    'sampleIds' => [
                        '1SS08' => '1SS08',
                        '1PS08' => '1PS08'
                    ]
                ],
                '3' => [
                    'sampleIds' => [
                        '1SS08' => '1SS08',
                        '1PS08' => '1PS08'
                    ]
                ],
                '3.1' => [
                    'sampleIds' => [
                        '1SS08' => '2SST8',
                        '1PS08' => '2PST8'
                    ]
                ],
                '4' => [
                    'sampleIds' => [
                        '1SS08' => '2SST8',
                        '1PS08' => '2PST8'
                    ]
                ]
            ]
        ];

        // For HPO orders samples display text changes for only swinging bucket centrifuge type
        foreach ($data as $centrifugeType => $values) {
            $order = $this->createOrder([
                'createdTs' => new \DateTime('2021-01-01 08:00:00'),
                'printedTs' => new \DateTime('2021-01-01 09:00:00'),
                'collectedTs' => new \DateTime('2021-01-01 10:00:00'),
                'processedTs' => new \DateTime('2021-01-01 11:00:00'),
                'finalizedTs' => new \DateTime('2021-01-01 12:00:00'),
                'processedCentrifugeType' => $centrifugeType
            ]);
            foreach ($values as $version => $value) {
                $order->setVersion($version);
                $order->loadSamplesSchema();
                $samplesInformation = $order->getSamplesInformation();
                foreach ($value['sampleIds'] as $sample => $sampleId) {
                    $this->assertSame($sampleId, $samplesInformation[$sample]['sampleId']);
                }
            }
        }
    }

    public function testDvKitOrdersSampleIds()
    {
        $data = [
            '1' => [
                'sampleIds' => [
                    '1SST8' => '1SST8',
                    '1PST8' => '1PST8'
                ]
            ],
            '2' => [
                'sampleIds' => [
                    '1SS08' => '1SS08',
                    '1PS08' => '1PS08'
                ]
            ],
            '3' => [
                'sampleIds' => [
                    '1SS08' => '1SS08',
                    '1PS08' => '1PS08'
                ]
            ],
            '3.1' => [
                'sampleIds' => [
                    '1SS08' => '1SS08',
                    '1PS08' => '1PS08'
                ]
            ],
            '4' => [
                'sampleIds' => [
                    '1SS08' => '1SS08',
                    '1PS08' => '1PS08'
                ]
            ]
        ];

        // For DV orders samples display text is not changed regardless of centrifuge type
        $centrifugeTypes = ['swinging_bucket', 'fixed_angle'];
        foreach ($centrifugeTypes as $centrifugeType) {
            $order = $this->createOrder([
                'createdTs' => new \DateTime('2021-01-01 08:00:00'),
                'printedTs' => new \DateTime('2021-01-01 09:00:00'),
                'collectedTs' => new \DateTime('2021-01-01 10:00:00'),
                'processedTs' => new \DateTime('2021-01-01 11:00:00'),
                'finalizedTs' => new \DateTime('2021-01-01 12:00:00'),
                'type' => 'kit',
                'processedCentrifugeType' => $centrifugeType
            ]);
            foreach ($data as $version => $value) {
                $order->setVersion($version);
                $order->loadSamplesSchema();
                $samplesInformation = $order->getSamplesInformation();
                foreach ($value['sampleIds'] as $sample => $sampleId) {
                    $this->assertSame($sampleId, $samplesInformation[$sample]['sampleId']);
                }
            }
        }
    }

    public function testCanCancel()
    {
        $orderHistory = $this->createOrderHistory([
            'type' => 'cancel'
        ]);
        $order = $this->createOrder([
            'finalizedTs' => new \DateTime('2021-01-01 12:00:00'),
            'mayoId' => 'WEB123456789',
            'rdrId' => 'WEB123456789'
        ]);
        $order->setHistory($orderHistory);

        // Assert can cancel
        $this->assertSame(false, $order->canCancel());
        $orderHistory->setType('unlock');
        $this->assertSame(false, $order->canCancel());
        $orderHistory->setType('active');
        $order->setRdrId('');
        $this->assertSame(false, $order->canCancel());
        $order->setRdrId('WEB123456789');
        $this->assertSame(true, $order->canCancel());
    }

    public function testCanRestore()
    {
        $orderHistory = $this->createOrderHistory([
            'type' => 'cancel'
        ]);
        $order = $this->createOrder([
            'mayoId' => 'WEB123456789',
            'rdrId' => 'WEB123456789'
        ]);
        $order->setHistory($orderHistory);

        // Assert can restore
        $this->assertSame(false, $order->canRestore());
        $order->setVersion('3.1');
        $order->setFinalizedTs(new \DateTime('2021-01-01 12:00:00'));
        $orderHistory->setType('unlock');
        $this->assertSame(false, $order->canRestore());
        $orderHistory->setType('active');
        $order->setRdrId('');
        $this->assertSame(false, $order->canRestore());
        $orderHistory->setType('cancel');
        $order->setRdrId('WEB123456789');
        $this->assertSame(true, $order->canRestore());
    }

    public function testCanUnlock()
    {
        $orderHistory = $this->createOrderHistory([
            'type' => 'active'
        ]);
        $order = $this->createOrder([
            'mayoId' => 'WEB123456789',
            'rdrId' => 'WEB123456789'
        ]);
        $order->setHistory($orderHistory);

        // Assert can restore
        $this->assertSame(false, $order->canUnlock());
        $order->setVersion('3.1');
        $order->setFinalizedTs(new \DateTime('2021-01-01 12:00:00'));
        $order->setRdrId('');
        $this->assertSame(false, $order->canUnlock());
        $order->setRdrId('WEB123456789');
        $orderHistory->setType('unlock');
        $this->assertSame(false, $order->canUnlock());
        $orderHistory->setType('cancel');
        $this->assertSame(false, $order->canUnlock());
        $orderHistory->setType('active');
        $this->assertSame(true, $order->canUnlock());
    }
}
