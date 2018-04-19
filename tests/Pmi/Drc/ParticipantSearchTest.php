<?php
use Pmi\Drc\MockParticipantSearch;

class ParticipantSearchTest extends \PHPUnit_Framework_TestCase
{
    protected function getDrcParticipantClient()
    {
        $client = new MockParticipantSearch();
        return $client;
    }

    protected function assertIsValidParticipant($participant)
    {
        $this->assertObjectHasAttribute('firstName', $participant);
        $this->assertObjectHasAttribute('lastName', $participant);
        $this->assertObjectHasAttribute('dob', $participant);
        $this->assertObjectHasAttribute('id', $participant);
        $this->assertInstanceOf(\DateTime::class, $participant->dob);
    }

    public function testSearch()
    {
        $client = $this->getDrcParticipantClient();
        $parameters = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dob' => new \DateTime('1980-01-01')
        ];
        $result = $client->search($parameters);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey(0, $result);
        $participant = $result[0];
        $this->assertIsValidParticipant($participant);
    }

    public function testEmailSearch()
    {
        $client = $this->getDrcParticipantClient();
        $parameters = [
            'email' => 'test@example.com'
        ];
        $result = $client->search($parameters);
        $this->assertTrue(is_array($result));
        $this->assertEquals(1, count($result));
        $this->assertArrayHasKey(0, $result);
        $participant = $result[0];
        $this->assertIsValidParticipant($participant);
    }

    public function testRetrieve()
    {
        $client = $this->getDrcParticipantClient();
        $participant = $client->getById(1001);
        $this->assertIsValidParticipant($participant);
    }
}
