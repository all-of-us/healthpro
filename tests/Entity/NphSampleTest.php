<?php

namespace App\Tests\Entity;

class NphSampleTest extends NphTestCase
{
    /**
     * @dataProvider canUnlockDataProvider
     */

    public function testCanUnlock($sample, $canUnlock)
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        $sample['nphOrder'] = $nphOrder;
        $nphSample = $this->createNphSample($sample);
        $this->assertSame($canUnlock, $nphSample->canUnlock());
    }

    public function canUnlockDataProvider(): array
    {
        $finalizedTs = new \DateTime('2023-01-08 08:00:00');
        return [
            [
                [
                    'sampleId' => '1000000000',
                    'sampleCode' => 'SST8P5',
                    'finalizedTs' => $finalizedTs,
                    'modifyType' => 'cancel',
                ],
                false
            ],
            [
                [
                    'sampleId' => '1000000000',
                    'sampleCode' => 'SST8P5',
                    'finalizedTs' => $finalizedTs,
                    'modifyType' => 'unlock',
                ],
                false
            ],
            [
                [
                    'sampleId' => '1000000000',
                    'sampleCode' => 'SST8P5',
                ],
                false
            ],
            [
                [
                    'sampleId' => '1000000000',
                    'sampleCode' => 'SST8P5',
                    'finalizedTs' => $finalizedTs,
                ],
                true
            ]
        ];
    }
}
