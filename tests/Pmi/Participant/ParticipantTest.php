<?php
use Pmi\Entities\Participant;

class ParticipantTest extends \PHPUnit_Framework_TestCase
{
    public function testMayolinkDob()
    {
        $participant = new Participant([
            'dob' => new \DateTime('1999-05-20'),
        ]);
        $this->assertSame('1999-05-20', $participant->dob->format('Y-m-d'));
        $this->assertSame('1960-05-20', $participant->getMayolinkDob()->format('Y-m-d'));
        $this->assertSame('1960-01-01', $participant->getMayolinkDob('kit')->format('Y-m-d'));

        $participant = new Participant([
            'dob' => new \DateTime('1996-02-29'),
        ]);
        $this->assertSame('1996-02-29', $participant->dob->format('Y-m-d'));
        $this->assertSame('1960-02-29', $participant->getMayolinkDob()->format('Y-m-d'));
        $this->assertSame('1960-01-01', $participant->getMayolinkDob('kit')->format('Y-m-d'));
    }
}
