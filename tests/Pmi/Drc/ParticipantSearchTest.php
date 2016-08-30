<?php
use Pmi\Drc\ParticipantSearch;

class ParticipantSearchTest extends \PHPUnit_Framework_TestCase
{
    public function testSearch()
    {
        $search = new ParticipantSearch();
        $parameters = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dob' => new \DateTime('1980-01-01')
        ];
        $result = $search->search($parameters);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey(0, $result);
        $participant = $result[0];
        $this->assertObjectHasAttribute('firstName', $participant);
        $this->assertObjectHasAttribute('lastName', $participant);
        $this->assertObjectHasAttribute('dob', $participant);
        $this->assertInstanceOf(\DateTime::class, $participant->dob);
    }
}
