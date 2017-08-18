<?php
use Pmi\Entities\Participant;

class OrderNotesIdentifierTest extends \PHPUnit_Framework_TestCase
{
    public function testName()
    {
        $participant = new Participant((object)[
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);
        $type = $participant->checkIdentifiers('My name John Doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name John-Doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name John.Doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name John,Doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name John   Doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name Doe John');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name Doe-John');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name Doe.John');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name Doe,John');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name Doe   John');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name john doe');
        $this->assertSame('name', $type[0]);
        $type = $participant->checkIdentifiers('My name John');
        $this->assertFalse($type);
    }

    public function testDob()
    {
        $participant = new Participant((object)[
            'dateOfBirth' => '1970-05-20'
        ]);
        $type = $participant->checkIdentifiers('My dob 05.20.70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 05-20-70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 05.20.70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 05/20/1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 05-20-1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 05.20.1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20/05/70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20-05-70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20.05.70');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20/05/1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20-05-1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 20.05.1970');
        $this->assertSame('dob', $type[0]);
        $type = $participant->checkIdentifiers('My dob 06/20/1970');
        $this->assertFalse($type);
    }

    public function testPhoneNumber()
    {
        $participant = new Participant((object)[
            'phoneNumber' => '(987) 654-3210'
        ]);
        $type = $participant->checkIdentifiers('My phone number (987) 654-3210');
        $this->assertSame('phone', $type[0]);
        $type = $participant->checkIdentifiers('My phone number 987-654-3210');
        $this->assertSame('phone', $type[0]);
        $type = $participant->checkIdentifiers('My phone number 9876543210');
        $this->assertSame('phone', $type[0]);
        $type = $participant->checkIdentifiers('My phone number (987) 654.3210');
        $this->assertSame('phone', $type[0]);
        $type = $participant->checkIdentifiers('My phone number 987.654.3210');
        $this->assertSame('phone', $type[0]);
        $type = $participant->checkIdentifiers('My phone number 987.654.3219');
        $this->assertFalse($type);
    }

    public function testStreetAddress()
    {
        $participant = new Participant((object)[
            'streetAddress' => '1234 TEST RD'
        ]);
        $type = $participant->checkIdentifiers('My street address 1234 TEST RD');
        $this->assertSame('address', $type[0]);
        $type = $participant->checkIdentifiers('My street address 1234-TEST-RD');
        $this->assertSame('address', $type[0]);
        $type = $participant->checkIdentifiers('My street address 1234,TEST,RD');
        $this->assertSame('address', $type[0]);
        $type = $participant->checkIdentifiers('My street address 1234.TEST.RD');
        $this->assertSame('address', $type[0]);
        $type = $participant->checkIdentifiers('My street address 1234   TEST   RD');
        $this->assertSame('address', $type[0]);
        $type = $participant->checkIdentifiers('My street address 1234 TEST2 RD');
        $this->assertFalse($type);
    }

    public function testEmail()
    {
        $participant = new Participant((object)[
            'email' => 'test@example.com'
        ]);
        $type = $participant->checkIdentifiers('My email address test@example.com');
        $this->assertSame('email', $type[0]);
        $type = $participant->checkIdentifiers('My email address test2@example.com');
        $this->assertFalse($type);
    }
}
