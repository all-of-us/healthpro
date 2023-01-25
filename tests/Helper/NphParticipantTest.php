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
            'DOB' => '1999-05-20',
        ]);
        $this->assertSame('10000000000000', $participant->id);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
    }
}
