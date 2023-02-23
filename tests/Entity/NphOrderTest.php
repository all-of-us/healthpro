<?php

namespace App\Tests\Entity;

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
}
