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

        $stringsWithIdentifiers = [
            'John Doe',
            'John-Doe',
            'John.Doe',
            'John,Doe',
            'John   Doe',
            'Doe John',
            'Doe-John',
            'Doe.John',
            'Doe,John',
            'Doe   John',
            'john doe',
            "john\ndoe",
            "john \n doe"
        ];
        $stringsWithoutIdentifiers = [
            'John',
            'John_Doe',
            'John0 Doe'
        ];

        foreach ($stringsWithIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('name', $match[0], $string);
            $this->assertSame($string, $match[1], $string);

            $stringWithOtherWords = "Some words before {$string} and after";
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('name', $match[0], $stringWithOtherWords);
            $this->assertSame($string, $match[1], $stringWithOtherWords);

            $stringWithExtraCharsBefore = "This{$string} should not match";
            $match = $participant->checkIdentifiers($stringWithExtraCharsBefore);
            $this->assertFalse($match, $stringWithExtraCharsBefore);

            $stringWithExtraCharsBefore = "This {$string}should not match";
            $match = $participant->checkIdentifiers($stringWithExtraCharsBefore);
            $this->assertFalse($match, $stringWithExtraCharsBefore);
        }

        foreach ($stringsWithoutIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertFalse($match, $string);
        }
    }

    public function testDob()
    {
        $participant = new Participant((object)[
            'dateOfBirth' => '1970-05-20'
        ]);

        $stringsWithIdentifiers = [
            '05.20.70',
            '05-20-70',
            '05.20.70',
            '05/20/1970',
            '05-20-1970',
            '05.20.1970',
            '20/05/70',
            '20-05-70',
            '20.05.70',
            '20/05/1970',
            '20-05-1970',
            '20.05.1970'
        ];
        $stringsWithoutIdentifiers = [
            '06/20/1970'
        ];

        foreach ($stringsWithIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('dob', $match[0], $string);
            $this->assertSame($string, $match[1], $string);

            $stringWithOtherWords = "Some words before {$string} and after";
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('dob', $match[0], $stringWithOtherWords);
            $this->assertSame($string, $match[1], $stringWithOtherWords);
        }

        foreach ($stringsWithoutIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertFalse($match, $string);
        }
    }

    public function testPhoneNumber()
    {
        $participant = new Participant((object)[
            'phoneNumber' => '(987) 654-3210'
        ]);

        $stringsWithIdentifiers = [
            '(987) 654-3210',
            '987-654-3210',
            '9876543210',
            '(987) 654.3210',
            '987.654.3210',
            '987 - 654 - 3210'
        ];

        $stringsWithoutIdentifiers = [
            '987w654w3210',
            '987.654.3219'
        ];

        foreach ($stringsWithIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('phone', $match[0], $string);
            $this->assertSame($string, $match[1], $string);

            $stringWithOtherWords = "Some words before {$string} and after";
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('phone', $match[0], $stringWithOtherWords);
            $this->assertSame($string, $match[1], $stringWithOtherWords);
        }

        foreach ($stringsWithoutIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertFalse($match, $string);
        }
    }

    public function testStreetAddress()
    {
        $participant = new Participant((object)[
            'streetAddress' => '1234 TEST RD'
        ]);

        $stringsWithIdentifiers = [
            '1234 TEST RD',
            '1234-TEST-RD',
            '1234,TEST,RD',
            '1234.TEST.RD',
            '1234   TEST   RD',
        ];

        $stringsWithoutIdentifiers = [
            '1234 TEST2 RD'
        ];

        foreach ($stringsWithIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('address', $match[0], $string);
            $this->assertSame($string, $match[1], $string);

            $stringWithOtherWords = "Some words before {$string} and after";
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('address', $match[0], $stringWithOtherWords);
            $this->assertSame($string, $match[1], $stringWithOtherWords);

            $stringWithExtraCharsBefore = "This{$string} should not match";
            $match = $participant->checkIdentifiers($stringWithExtraCharsBefore);
            $this->assertFalse($match, $stringWithExtraCharsBefore);

            $stringWithExtraCharsBefore = "This {$string}should not match";
            $match = $participant->checkIdentifiers($stringWithExtraCharsBefore);
            $this->assertFalse($match, $stringWithExtraCharsBefore);
        }

        foreach ($stringsWithoutIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertFalse($match, $string);
        }
    }

    public function testEmail()
    {
        $participant = new Participant((object)[
            'email' => 'test@example.com'
        ]);

        $stringsWithIdentifiers = [
            'test@example.com',
        ];

        $stringsWithoutIdentifiers = [
            'test2@example.com'
        ];

        foreach ($stringsWithIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('email', $match[0], $string);
            $this->assertSame($string, $match[1], $string);

            $stringWithOtherWords = "Some words before {$string} and after";
            $match = $participant->checkIdentifiers($string);
            $this->assertSame('email', $match[0], $stringWithOtherWords);
            $this->assertSame($string, $match[1], $stringWithOtherWords);
        }

        foreach ($stringsWithoutIdentifiers as $string) {
            $match = $participant->checkIdentifiers($string);
            $this->assertFalse($match, $string);
        }
    }
}
