<?php

namespace App\Tests\Entity;

use App\Entity\NphSample;

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
        $expectedAliquotIds = ['11111111111'];
        $this->assertSame($expectedStatus, $sample->getNphAliquotsStatus());
        $this->assertSame($expectedAliquotIds, $sample->getNphAliquotIds());
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
            'test' => 'SST8.5',
            'description' => 'Test Description',
            'collected' => '2023-01-08T08:00:00Z',
            'finalized' => '2023-01-08T08:00:00Z'
        ];
        $this->assertSame($expectedSampleObj, $sample->getRdrSampleObj('SST8.5', 'Test Description'));

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
     * @dataProvider isDisabledDataProvider
     */
    public function testIsDisabled($rdrId, $modifyType, $expectedResult): void
    {
        $nphSample = new NphSample();
        if ($rdrId) {
            $nphSample->setRdrId($rdrId);
        }
        $nphSample->setModifyType($modifyType);
        $this->assertEquals($expectedResult, $nphSample->isDisabled());
    }

    public function isDisabledDataProvider(): array
    {
        $finalizedTs = new \DateTime('2023-03-06 08:00:00');
        return [
            // Test cases where isDisabled should return false
            [null, NphSample::RESTORE, false],
            [null, NphSample::UNLOCK, false],
            [100, NphSample::UNLOCK, false],

            // Test cases where isDisabled should return true
            [101, null, true],
            [102, NphSample::CANCEL, true],
            [null, NphSample::CANCEL, true],
        ];
    }

    /**
     * @dataProvider isUnlockedDataProvider
     */
    public function testIsUnlocked($modifyType, $expectedResult): void
    {
        $nphSample = new NphSample();
        $nphSample->setModifyType($modifyType);
        $this->assertEquals($expectedResult, $nphSample->isUnlocked());
    }

    public function isUnlockedDataProvider(): array
    {
        return [
            [NphSample::REVERT, false],
            [NphSample::UNLOCK, true],
            [NphSample::CANCEL, false],
            [NphSample::EDITED, false],
            [NphSample::RESTORE, false],
        ];
    }

    /**
     * @dataProvider sampleMetadataProvider
     */
    public function testGetSampleMetadataArray(string $sampleMetadata, array $expected)
    {
        $nphSample = new NphSample();
        $nphSample->setSampleMetadata($sampleMetadata);
        $result = $nphSample->getSampleMetadataArray();
        $this->assertEquals($expected, $result);
    }

    public function sampleMetadataProvider(): array
    {
        return [
            [
                '{"urineColor":"1","urineClarity":"slightly_cloudy"}',
                [
                    'urineColor' => 'Color 1',
                    'urineClarity' => 'Slightly Cloudy',
                ],
            ],
            [
                '{"urineColor":"2","urineClarity":"turbid"}',
                [
                    'urineColor' => 'Color 2',
                    'urineClarity' => 'Turbid',
                ],
            ]
        ];
    }
}
