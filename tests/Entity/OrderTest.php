<?php

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderTest extends KernelTestCase
{
    private $em;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::$container->get(EntityManagerInterface::class);

    }
    protected function getUser()
    {
        $user = new User;
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

    protected function createOrderHistory($params = [])
    {
        $order = new OrderHistory();
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
        $this->assertEquals('Patient/P123456789', $order->getRdrObject('test', 'test', 'test', 'test')->subject);
        // Assert createdInfo
        $this->assertEquals('test@example.com', $order->getRdrObject('test', 'test', 'test', 'test')->createdInfo['author']['value']);
        $this->assertEquals('test', $order->getRdrObject('test', 'test', 'test', 'test')->createdInfo['site']['value']);
        // Assert identifiers orderId and mayoId
        $this->assertEquals('0123456789', $order->getRdrObject('test', 'test', 'test', 'test')->identifier[0]['value']);
        $this->assertEquals('WEB123456789', $order->getRdrObject('test', 'test', 'test', 'test')->identifier[1]['value']);
        // Assert processed sample codes (swinging_bucket)
        $this->assertEquals('1SST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[0]['test']);
        $this->assertEquals('1PST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[1]['test']);
        // Assert processingRequired
        $this->assertEquals('1', $order->getRdrObject('test', 'test', 'test', 'test')->samples[0]['processingRequired']);
        $this->assertEquals('1', $order->getRdrObject('test', 'test', 'test', 'test')->samples[1]['processingRequired']);

        // DV order
        $orderData['type'] = 'kit';
        $orderData['processedCentrifugeType'] = 'fixed_angle';
        $order = $this->createOrder($orderData);
        $order->loadSamplesSchema();
        // Assert processed sample codes (fixed_angle)
        $this->assertEquals('2SST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[0]['test']);
        $this->assertEquals('2PST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[1]['test']);


        // Version 3.2
        // HPO Order
        $orderData = $this->getOrderData('3.2');
        $order = $this->createOrder($orderData);
        $order->loadSamplesSchema();
        // Assert processed sample codes (swinging_bucket)
        $this->assertEquals('1SST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[0]['test']);
        $this->assertEquals('1PS4A', $order->getRdrObject('test', 'test', 'test', 'test')->samples[1]['test']);
        $this->assertEquals('1PS4B', $order->getRdrObject('test', 'test', 'test', 'test')->samples[2]['test']);

        // DV order
        $orderData['type'] = 'kit';
        $orderData['processedCentrifugeType'] = 'fixed_angle';
        $order = $this->createOrder($orderData);
        $order->loadSamplesSchema();
        // Assert processed sample codes (fixed_angle)
        $this->assertEquals('2SST8', $order->getRdrObject('test', 'test', 'test', 'test')->samples[0]['test']);
        $this->assertEquals('2PS4A', $order->getRdrObject('test', 'test', 'test', 'test')->samples[1]['test']);
        $this->assertEquals('2PS4B', $order->getRdrObject('test', 'test', 'test', 'test')->samples[2]['test']);
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
                'value' => 'test'
            ]
        ];
        $this->assertEquals('Test reason', $order->getEditRdrObject('test', 'test', 'test', 'test', 'test')->amendedReason);
        $this->assertEquals($amendedInfo, $order->getEditRdrObject('test', 'test', 'test', 'test', 'test')->amendedInfo);
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
                '3.2' => [
                    'sampleIds' => [
                        '1SS08' => '1SST8',
                        'PS04A' => '1PS4A',
                        'PS04B' => '1PS4B'
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
                '3.2' => [
                    'sampleIds' => [
                        '1SS08' => '2SST8',
                        'PS04A' => '2PS4A',
                        'PS04B' => '2PS4B'
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
        // Sample id is the test code that we send to mayolink API
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
            '3.2' => [
                'sampleIds' => [
                    '1SS08' => '1SS08',
                    'PS04A' => 'PS04A',
                    'PS04B' => 'PS04B'
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
        // Sample id is the test code that we send to mayolink API
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

        // Assert can unlock
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
        $order->setVersion('');
        $this->assertSame(false, $order->canUnlock());
    }

    /**
     * @dataProvider orderTypeProvider
     */
    public function testOrderTypeDisplayText($orderDisplayText, $orderType, $requestedSamples)
    {
        $orderData = $this->getOrderData();
        $order = $this->createOrder($orderData);

        $order->setType($orderType);
        $order->setRequestedSamples($requestedSamples);
        self::assertEquals($orderDisplayText, $order->getOrderTypeDisplayText());
    }

    public function orderTypeProvider(): array
    {
        return [
            ['Full Kit', 'kit', null],
            ['Urine', 'kit', '["1UR10"]'],
            ['Custom HPO', null, '["1SS08", "1PS08", "1UR10"]'],
            ['Full HPO', null, null],
            ['Saliva', 'saliva', null],
            ['Urine', null, '["1UR10"]']
        ];
    }

    /**
     * @dataProvider getBiobankChangesDataProvider
     */
    public function testGetBiobankChanges($data, $json)
    {
        $createdTs = new \DateTime('2022-01-01 08:00:00');
        $finalizedTs = new \DateTime('2022-01-02 08:00:00');
        $finalizedSamples = ["1SS08", "1PS08", "1HEP4", "1ED04", "1ED10", "1CFD9", "1PXR2", "1UR10"];
        $centrifugeType = 'swinging_bucket';
        $orderData = [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => $createdTs,
            'participantId' => 'P123456789'
        ];
        $orderData = array_merge($orderData, $data);
        $collectedTs = $data['collectedTs'];
        $order = $this->createOrder($orderData);
        $order->checkBiobankChanges($collectedTs, $finalizedTs, $finalizedSamples, '', $centrifugeType, 2);
        self::assertEquals($order->getBiobankChanges(), $json);
    }

    public function getBiobankChangesDataProvider(): array
    {
        $ts = new \DateTime('2022-01-03 08:00:00');
        return [
            [
                [
                    'collectedTs' => null,
                    'processedTs' => null,
                ],
                '{"collected":{"time":1641024000,"user":null,"site":"test","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]},"processed":{"time":1641024000,"user":null,"site":"test","samples":["1SS08","1PS08"],"samples_ts":{"1SS08":1641024000,"1PS08":1641024000},"centrifuge_type":"swinging_bucket"},"finalized":{"time":1641110400,"site":"test","user":null,"notes":"","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]}}'
            ],
            [
                [
                    'collectedTs' => $ts,
                    'processedTs' => null,
                ],
                '{"collected":{"site":"test","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]},"processed":{"time":1641024000,"user":null,"site":"test","samples":["1SS08","1PS08"],"samples_ts":{"1SS08":1641024000,"1PS08":1641024000},"centrifuge_type":"swinging_bucket"},"finalized":{"time":1641110400,"site":"test","user":null,"notes":"","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]}}'
            ],
            [
                [
                    'collectedTs' => null,
                    'processedTs' => $ts,
                    'processedSamplesTs' => '{"1SS08":1641024000,"1PS08":1641024000}'
                ],
                '{"collected":{"time":1641024000,"user":null,"site":"test","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]},"processed":{"site":"test","samples":["1SS08","1PS08"],"samples_ts":{"1SS08":1641024000,"1PS08":1641024000},"centrifuge_type":"swinging_bucket"},"finalized":{"time":1641110400,"site":"test","user":null,"notes":"","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]}}'
            ],
            [
                [
                    'collectedTs' => $ts,
                    'processedTs' => $ts,
                    'processedSamplesTs' => '{"1SS08":1641024000,"1PS08":1641024000}'
                ],
                '{"collected":{"site":"test","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]},"processed":{"site":"test","samples":["1SS08","1PS08"],"samples_ts":{"1SS08":1641024000,"1PS08":1641024000},"centrifuge_type":"swinging_bucket"},"finalized":{"time":1641110400,"site":"test","user":null,"notes":"","samples":["1SS08","1PS08","1HEP4","1ED04","1ED10","1CFD9","1PXR2","1UR10"]}}'
            ],
        ];
    }

    /**
     * @dataProvider biobankOrderDataProvider
     */
    public function testBiobankOrderChanges($data, $result)
    {
        $createdTs = new \DateTime('2022-01-01 08:00:00');
        $finalizedTs = new \DateTime('2022-01-02 08:00:00');
        $finalizedSamples = ["1SS08", "1PS08", "1HEP4", "1ED04", "1ED10", "1CFD9", "1PXR2", "1UR10"];
        $centrifugeType = 'swinging_bucket';
        $orderData = [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => $createdTs,
            'participantId' => 'P123456789'
        ];
        $collectedTs = $data['collectedTs'];
        $orderData = array_merge($orderData, $data);
        $order = $this->createOrder($orderData);
        if (empty($collectedTs)) {
            $order->setCollectedTs($createdTs);
        }
        $order->checkBiobankChanges($collectedTs, $finalizedTs, $finalizedSamples, '', $centrifugeType, 2);
        self::assertEquals($order->getCollectedTs(), $result['collectedTs']);
        self::assertEquals($order->getProcessedTs(), $result['processedTs']);
        self::assertEquals($order->getFinalizedTs(), $finalizedTs);
    }

    public function biobankOrderDataProvider(): array
    {
        $createdTs = new \DateTime('2022-01-01 08:00:00');
        $ts = new \DateTime('2022-01-03 08:00:00');
        return [
            [
                [
                    'collectedTs' => null,
                    'processedTs' => null,
                ],
                [
                    'collectedTs' => $createdTs,
                    'processedTs' => $createdTs,
                ],
            ],
            [
                [
                    'collectedTs' => $ts,
                    'processedTs' => null,
                ],
                [
                    'collectedTs' => $ts,
                    'processedTs' => $createdTs,
                ],
            ],
            [
                [
                    'collectedTs' => null,
                    'processedTs' => $ts,
                    'processedSamplesTs' => '{"1SS08":1641024000,"1PS08":1641024000}'
                ],
                [
                    'collectedTs' => $createdTs,
                    'processedTs' => $ts,
                ],
            ],
            [
                [
                    'collectedTs' => $ts,
                    'processedTs' => $ts,
                    'processedSamplesTs' => '{"1SS08":1641024000,"1PS08":1641024000}'
                ],
                [
                    'collectedTs' => $ts,
                    'processedTs' => $ts,
                ],
            ],
        ];
    }

    /**
     * @dataProvider orderSampleVersionsDataProvider
     */
    public function testOrderSampleVersion($sampleVersion, $resultSampleVersion)
    {
        $orderData = $this->getOrderData();
        $order = $this->createOrder($orderData);
        $this->em->persist($order);
        $this->em->flush();
        $params = [
            'order_samples_version' => '3.2'
        ];
        $order->setVersion($sampleVersion);
        $order->loadSamplesSchema($params);
        $this->assertSame($resultSampleVersion, $order->getCurrentVersion());
    }

    public function orderSampleVersionsDataProvider()
    {
        return [
            ['', '1'],
            ['2', '2'],
            ['3', '3'],
            ['3.1', '3.1'],
            ['3.2', '3.2']
        ];
    }

    /**
     * @dataProvider orderDataProvider
     */
    public function testHideTrackingFieldByDefault(?string $fedexTracking, ?string $orderType, bool $expected): void
    {
        $order = new Order();
        $order->setFedexTracking($fedexTracking);
        $order->setType($orderType);
        $this->assertEquals($expected, $order->hideTrackingFieldByDefault());
    }

    public function orderDataProvider(): array
    {
        return [
            'noFedexTrackingAndTypeIsKit' => [
                'fedexTracking' => null,
                'orderType' => Order::ORDER_TYPE_KIT,
                'expected' => true
            ],
            'fedexTrackingAndTypeIsKit' => [
                'fedexTracking' => '123456789',
                'orderType' => Order::ORDER_TYPE_KIT,
                'expected' => false,
            ],
            'noFedexTrackingAndTypeIsDiversion' => [
                'fedexTracking' => null,
                'orderType' => Order::ORDER_TYPE_DIVERSION,
                'expected' => true,
            ],
            'fedexTrackingAndTypeIsDiversion' => [
                'fedexTracking' => '123456789',
                'orderType' => Order::ORDER_TYPE_DIVERSION,
                'expected' => false,
            ],
            'fedexTrackingAndTypeIsRegular' => [
                'fedexTracking' => '123456789',
                'orderType' => null,
                'expected' => false,
            ],
        ];
    }
}
