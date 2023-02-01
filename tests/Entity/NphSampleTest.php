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

    /**
     * @dataProvider aliquotDataProvider
     */
    public function testGetNphAliquotsStatus($aliquotData)
    {
        $sample = $this->createOrderAndSample();
        $aliquotData['nphSample'] = $sample;
        $aliquotData = array_merge($this->getAliquotData(), $aliquotData);
        $this->createNphAliquot($aliquotData);
        $expectedStatus = [
            $aliquotData['aliquotId'] => $aliquotData['status']
        ];
        $this->assertSame($expectedStatus, $sample->getNphAliquotsStatus());
    }

    public function aliquotDataProvider(): array
    {
        return [
            [
                [
                    'status' => 'cancel',
                ]
            ],
            [
                [
                    'status' => 'restore',
                ]
            ],
            [
                [
                    'status' => null,
                ]
            ]
        ];
    }

    public function testGetRdrSampleObj()
    {
        $sample = $this->createOrderAndSample();
        $aliquotData['nphSample'] = $sample;
        $aliquotData = array_merge($this->getAliquotData(), $aliquotData);
        $this->createNphAliquot($aliquotData);
        $expectedSampleObj = [
            'test' => 'SST8P5',
            'description' => 'Test Description',
            'collected' => '2023-01-08T08:00:00Z',
            'finalized' => '2023-01-08T08:00:00Z'
        ];
        $this->assertSame($expectedSampleObj, $sample->getRdrSampleObj('Test Description'));

        $aliquotInfo = [
            'SST8P5A1' => [
                'identifier' => 'SSTS1',
                'container' => '1.4mL Matrix tube 1000μL',
                'description' => '1.4mL Matrix tube'
            ]
        ];
        $expectedAliquotSampleObj = [
            [
                'id' => '11111111111',
                'identifier' => 'SSTS1',
                'container' => '1.4mL Matrix tube 1000μL',
                'description' => '1.4mL Matrix tube',
                'volume' => 500.0,
                'collected' => '2023-01-08T08:00:00Z',
                'units' => 'uL'
            ]
        ];
        $this->assertSame($expectedAliquotSampleObj, $sample->getRdrAliquotsSampleObj($aliquotInfo));
    }

    /**
     * @dataProvider addNewAliquotsDataProvider
     */
    public function testAllowAddNewAliquots($orderType, $canAddNewAliquots)
    {
        $orderData['orderType'] = $orderType;
        $orderData = array_merge($this->getOrderData(), $orderData);
        $nphOrder = $this->createNphOrder($orderData);
        $sampleData = [
            'nphOrder' => $nphOrder,
            'finalizedTs' => null
        ];
        $sampleData = array_merge($this->getSampleData(), $sampleData);
        $nphSample = $this->createNphSample($sampleData);
        $this->assertSame($canAddNewAliquots, $nphSample->allowAddNewAliquots());
    }

    public function addNewAliquotsDataProvider(): array
    {
        return [
            [
                'blood',
                true
            ],
            [
                'stool',
                false
            ],
            [
                'nail',
                false
            ],
            [
                'hair',
                false
            ]
        ];
    }
}
