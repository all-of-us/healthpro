<?php

namespace App\Tests\Service;

use App\Helper\NphParticipant;
use App\Service\Nph\NphParticipantReviewService;
use App\Service\Nph\NphParticipantSummaryService;

class NphParticipantReviewServiceTest extends ServiceTestCase
{
    protected NphParticipantReviewService $service;

    public function setup(): void
    {
        $mockNphParticipantSummaryService = $this->createMock(NphParticipantSummaryService::class);
        $mockNphParticipantSummaryService->method('getParticipantById')->willReturn(new NphParticipant());
        $this->service = new NphParticipantReviewService($mockNphParticipantSummaryService);
    }

    /**
     * @dataProvider samplesDataProvider
     */
    public function testGetTodaysSamples(array $samples, bool $biobankView, array $expectedResult)
    {
        $result = $this->service->getTodaysSamples($samples, $biobankView);
        $this->assertEquals($expectedResult, $result);
    }

    public function samplesDataProvider(): array
    {
        return [
            [
                [
                    [
                        'participantId' => 1,
                        'module' => 1,
                        'createdCount' => 2,
                        'email' => 'example1@example.com',
                        'sampleId' => '123,456',
                        'sampleCode' => 'P800,SALIVA',
                        'createdTs' => '2023-05-30 10:00:00,2023-05-30 11:00:00',
                        'collectedTs' => '2023-05-30 10:30:00,2023-05-30 11:30:00',
                        'finalizedTs' => '2023-05-30 11:00:00,2023-05-30 12:00:00',
                    ],
                    [
                        'participantId' => 2,
                        'module' => 2,
                        'createdCount' => 1,
                        'email' => 'example2@example.com',
                        'sampleId' => '789',
                        'sampleCode' => 'EDTA10',
                        'createdTs' => '2023-05-30 12:00:00',
                        'collectedTs' => '2023-05-30 12:30:00',
                        'finalizedTs' => '2023-05-30 13:00:00',
                    ],
                ],
                false,
                [
                    'samples' => [
                        [
                            'participantId' => 1,
                            'module' => 1,
                            'createdCount' => 2,
                            'email' => ['example1@example.com'],
                            'sampleId' => ['123', '456'],
                            'sampleCode' => ['P800', 'SALIVA'],
                            'createdTs' => ['2023-05-30 10:00:00', '2023-05-30 11:00:00'],
                            'collectedTs' => ['2023-05-30 10:30:00', '2023-05-30 11:30:00'],
                            'finalizedTs' => ['2023-05-30 11:00:00', '2023-05-30 12:00:00'],
                            'participant' => new NphParticipant()
                        ],
                        [
                            'participantId' => 2,
                            'module' => 2,
                            'createdCount' => 1,
                            'email' => ['example2@example.com'],
                            'sampleId' => ['789'],
                            'sampleCode' => ['EDTA10'],
                            'createdTs' => ['2023-05-30 12:00:00'],
                            'collectedTs' => ['2023-05-30 12:30:00'],
                            'finalizedTs' => ['2023-05-30 13:00:00'],
                            'participant' => new NphParticipant()
                        ],
                    ],
                    'rowCounts' => [
                        1 => [
                            'participantRow' => 3,
                            'module1' => 3,
                        ],
                        2 => [
                            'participantRow' => 2,
                            'module2' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }
}
