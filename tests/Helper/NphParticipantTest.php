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
                3,
            ],
        ];
    }
}
