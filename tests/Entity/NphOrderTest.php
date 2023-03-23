<?php

namespace App\Tests\Entity;

use App\Entity\NphOrder;
use App\Entity\NphSample;

class NphOrderTest extends NphTestCase
{
    /**
     * @dataProvider canCancelRestoreDataProvider
     */

    public function testCanCancelRestore($samples, $canCancel, $canRestore)
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($canCancel, $nphOrder->canCancel());
        $this->assertSame($canRestore, $nphOrder->canRestore());
        $this->assertSame($canCancel, $nphOrder->canModify(NphSample::CANCEL));
        $this->assertSame($canRestore, $nphOrder->canModify(NphSample::RESTORE));
    }

    public function canCancelRestoreDataProvider(): array
    {
        return [
            [
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'cancel',
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'restore',
                    ],
                ],
                true,
                true
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'cancel',
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'cancel',
                    ],
                ],
                false,
                true
            ],
            [
                [
                    [
                        'sampleId' => '5000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'restore',
                    ],
                    [
                        'sampleId' => '6000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'restore',
                    ],
                ],
                true,
                false
            ],
            [
                [
                    [
                        'sampleId' => '7000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => '',
                    ],
                    [
                        'sampleId' => '8000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => '',
                    ],
                ],
                true,
                false
            ]
        ];
    }

    /**
     * @dataProvider isDisabledAndMetadataDisabledDataProvider
     */

    public function testIsDisabledAndMetadataDisabled($samples, $isDisabled, $isMetadataFieldDisabled)
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($isDisabled, $nphOrder->isDisabled());
        $this->assertSame($isMetadataFieldDisabled, $nphOrder->isMetadataFieldDisabled());
    }

    public function isDisabledAndMetadataDisabledDataProvider(): array
    {
        $finalizedTs = new \DateTime('2023-01-01 08:00:00');
        return [
            [
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'ST1',
                        'finalizedTs' => $finalizedTs
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'ST2',
                        'finalizedTs' => $finalizedTs
                    ],
                ],
                true,
                true
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'ST1',
                        'finalizedTs' => $finalizedTs
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                false,
                true
            ],
            [
                [
                    [
                        'sampleId' => '5000000000',
                        'sampleCode' => 'ST1',
                    ],
                    [
                        'sampleId' => '6000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                false,
                false
            ]
        ];
    }

    /**
     * @dataProvider getOrderStatusDataProvider
     */
    public function testGetOrderStatus($samples, $expectedStatus) {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($expectedStatus, $nphOrder->getStatus());
        $nphOrder->getStatus();
    }

    public function getOrderStatusDataProvider(): array {
        $finalizedTs = new \DateTime('2023-01-01 08:00:00');
        $collectedTs = new \DateTime('2023-01-01 08:00:00');
        return [
            [
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'ST1',
                        'finalizedTs' => $finalizedTs
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'ST2',
                        'finalizedTs' => $finalizedTs
                    ],
                ],
                'Finalized'
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'ST1',
                        'finalizedTs' => $finalizedTs
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                'In Progress'
            ],
            [
                [
                    [
                        'sampleId' => '5000000000',
                        'sampleCode' => 'ST1',
                        'collectedTs' => $collectedTs
                    ],
                    [
                        'sampleId' => '6000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                'In Progress'
            ],
            [
                [
                    [
                        'sampleId' => '7000000000',
                        'sampleCode' => 'ST1',
                        'collectedTs' => $collectedTs
                    ],
                    [
                        'sampleId' => '8000000000',
                        'sampleCode' => 'ST2',
                        'collectedTs' => $collectedTs
                    ],
                ],
                'Collected'
            ],
        ];
    }

    /**
     * @dataProvider collectedTimeProvider
     */

    public function testGetCollectedTs(array $samples, ?\DateTime $expectedCollectedTs): void
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($expectedCollectedTs, $nphOrder->getCollectedTs());
    }

    public function collectedTimeProvider(): array
    {
        $collectedTs1 = new \DateTime('2023-03-15 08:00:00');
        $collectedTs2 = new \DateTime('2023-03-16 08:00:00');
        return [
            [
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'ST1',
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'ST2'
                    ],
                ],
                null
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'ST1',
                        'collectedTs' => $collectedTs1
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'ST2',
                        'collectedTs' => $collectedTs2
                    ],
                ],
                $collectedTs1
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'ST1',
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'ST2',
                        'collectedTs' => $collectedTs2
                    ],
                ],
                $collectedTs2
            ]
        ];
    }

    /**
     * @dataProvider stoolTypeProvider
     */

    public function testIsStoolCollectedTsDisabled(string $orderType, array $samples, bool $expectedResult): void
    {
        $orderData = $this->getOrderData();
        $orderData['orderType'] = $orderType;
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($expectedResult, $nphOrder->isStoolCollectedTsDisabled());
    }

    public function stoolTypeProvider(): array
    {
        $collectedTs = new \DateTime();
        return [
            [
                NphOrder::TYPE_STOOL,
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'ST1'
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'ST2'
                    ],
                ],
                false
            ],
            [
                NphOrder::TYPE_STOOL,
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'ST1',
                        'collectedTs' => $collectedTs
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                false
            ],
            [
                NphOrder::TYPE_URINE,
                [
                    [
                        'sampleId' => '5000000000',
                        'sampleCode' => 'URINES',
                        'collectedTs' => $collectedTs
                    ],
                ],
                false
            ],
            [
                NphOrder::TYPE_STOOL,
                [
                    [
                        'sampleId' => '6000000000',
                        'sampleCode' => 'ST1',
                        'finalizedTs' => $collectedTs
                    ],
                    [
                        'sampleId' => '7000000000',
                        'sampleCode' => 'ST2',
                    ],
                ],
                true
            ],
        ];
    }
}
