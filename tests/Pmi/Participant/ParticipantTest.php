<?php
use Pmi\Entities\Participant;

class ParticipantTest extends PHPUnit\Framework\TestCase
{
    public function testMayolinkDob()
    {
        $participant = new Participant((object)[
            'dateOfBirth' => '1999-05-20',
        ]);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));

        $participant = new Participant((object)[
            'dateOfBirth' => '1996-02-29',
        ]);
        $this->assertSame('1996-02-29', $participant->dob->format('Y-m-d'));
        $this->assertSame('1933-03-03', $participant->getMayolinkDob()->format('Y-m-d'));
    }
}
