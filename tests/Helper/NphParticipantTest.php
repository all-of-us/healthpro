<?php

namespace App\Tests\Helper;

use App\Helper\NphParticipant;
use PHPUnit\Framework\TestCase;

class NphParticipantTest extends TestCase
{
    public function testNphParticipant(): void
    {
        $participant = new NphParticipant((object)[
            'participantNphId' => '10000000000000',
            'nphDateOfBirth' => '1999-05-20',
        ]);
        $this->assertSame('10000000000000', $participant->id);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
    }

    /**
     * @dataProvider enrollmentStatusProvider
     */
    public function testParticipantModule($enrollmentStatus, $expectedModule): void
    {
        $participant = new NphParticipant((object)[
            'nphEnrollmentStatus' => $enrollmentStatus
        ]);
        $this->assertSame($expectedModule, $participant->module);
    }

    public function enrollmentStatusProvider(): array
    {
        return [
            'module1_complete' => [
                [(object)['value' => 'module1_complete']],
                1,
            ],
            'module2_consented' => [
                [(object)['value' => 'module2_consented']],
                2,
            ],
            'module3_eligibilityConfirmed' => [
                [(object)['value' => 'module3_eligibilityConfirmed']],
                3,
            ],
            'no_match' => [
                [(object)['value' => 'invalid_status']],
                1,
            ],
            'multiple_statuses_match' => [
                [
                    (object)['value' => 'module2_complete'],
                    (object)['value' => 'module3_dietAssigned'],
                ],
                2,
            ],
            'multiple_statuses_match_time' => [
                [
                    (object)[
                        'time' => '2023-07-13T22:09:10',
                        'value' => 'nph_referred',
                    ],
                    (object)[
                        'time' => '2023-07-13T22:09:10',
                        'value' => 'module3_dietAssigned',
                    ],
                    (object)[
                        'time' => '2023-07-13T23:09:10',
                        'value' => 'module2_dietAssigned',
                    ],
                    (object)[
                        'time' => '2023-07-19T23:15:10',
                        'value' => 'module3_eligibilityConfirmed',
                    ],
                    (object)[
                        'time' => '2023-07-18T23:15:10',
                        'value' => 'module2_eligibilityConfirmed',
                    ]
                ],
                3,
            ],
            'multiple_statuses_match_equal_time' => [
                [
                    (object)[
                        'time' => '2023-07-13T22:09:10',
                        'value' => 'nph_referred',
                    ],
                    (object)[
                        'time' => '2023-07-13T22:09:10',
                        'value' => 'module3_dietAssigned',
                    ],
                    (object)[
                        'time' => '2023-07-13T23:09:10',
                        'value' => 'module2_dietAssigned',
                    ],
                    (object)[
                        'time' => '2023-07-18T23:15:10',
                        'value' => 'module3_eligibilityConfirmed',
                    ],
                    (object)[
                        'time' => '2023-07-18T23:15:10',
                        'value' => 'module2_eligibilityConfirmed',
                    ]
                ],
                2,
            ],
        ];
    }

    /**
     * @dataProvider moduleDietStatusProvider
     */
    public function testGetModuleDietStatus($nphModuleDietStatus, $module, $expected)
    {
        $nphModuleDietStatusField = "nphModule{$module}DietStatus";
        $participant = new NphParticipant((object)[
            $nphModuleDietStatusField => $nphModuleDietStatus
        ]);
        $moduleDietStatusField = "module{$module}DietStatus";
        $this->assertEquals($expected, $participant->{$moduleDietStatusField});
    }

    public function moduleDietStatusProvider(): array
    {
        return [
            'Completed Diet Status' => [
                'dietStatusData' => [
                    (object) [
                        'dietName' => 'ORANGE',
                        'dietStatus' => [
                            (object) [
                                'current' => false,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ],
                            (object) [
                                'current' => false,
                                'status' => 'completed',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ]
                ],
                'module' => 2,
                'expected' => ['ORANGE' => 'completed']
            ],
            'Discontinued Diet Status' => [
                'dietStatusData' => [
                    (object) [
                        'dietName' => 'ORANGE',
                        'dietStatus' => [
                            (object) [
                                'current' => false,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ],
                            (object) [
                                'current' => true,
                                'status' => 'discontinued',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ]
                ],
                'module' => 2,
                'expected' => ['ORANGE' => 'discontinued']
            ],
            'Started Diet Status' => [
                'dietStatusData' => [
                    (object) [
                        'dietName' => 'ORANGE',
                        'dietStatus' => [
                            (object) [
                                'current' => true,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ],
                    (object) [
                        'dietName' => 'PURPLE',
                        'dietStatus' => [
                            (object) [
                                'current' => true,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ],
                            (object) [
                                'current' => true,
                                'status' => 'continued',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ]
                ],
                'module' => 2,
                'expected' => ['ORANGE' => 'started', 'PURPLE' => 'started']
            ],
            'Incomplete Diet Status' => [
                'dietStatusData' => [
                    (object) [
                        'dietName' => 'BLUE',
                        'dietStatus' => [
                            (object) [
                                'current' => false,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ],
                    (object) [
                        'dietName' => 'ORANGE',
                        'dietStatus' => [
                            (object) [
                                'current' => false,
                                'status' => 'started',
                                'time' => '2023-01-01 12:01:00'
                            ],
                            (object) [
                                'current' => false,
                                'status' => 'discontinued',
                                'time' => '2023-01-01 12:01:00'
                            ],
                            (object) [
                                'current' => false,
                                'status' => 'continued',
                                'time' => '2023-01-01 12:01:00'
                            ]
                        ]
                    ]
                ],
                'module' => 2,
                'expected' => ['BLUE' => 'started', 'ORANGE' => 'incomplete']
            ],
        ];
    }
}
