<?php

namespace App\Tests\Helper;

use App\Helper\Import;
use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider("participantIdProvider")]
    public function testValidParticipantId($participantId, $isValid): void
    {
        $result = Import::isValidParticipantId($participantId);
        $this->assertEquals($result, $isValid);
    }

    public static function participantIdProvider()
    {
        return [
            ['P000000000', true],
            ['P000000001', true],
            ['0P000000000', false],
            ['P0000000002', false],
            ['100000000', false],
            ['20000000006', false]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("emailDataProvider")]
    public function testValidEmail($email, $isValid): void
    {
        $result = Import::isValidEmail($email);
        $this->assertEquals($result, $isValid);
    }

    public static function emailDataProvider()
    {
        return [
            ['test-1@pmi-ops.org', true],
            ['test-2@pmiops.org', false],
            ['test-3@ops-pmi.org', false],
            ['test-4@pmi-ops.org@ops-pmi.org', false],
            ['pmi-ops.org@ops-pmi.org', false]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("dateProvider")]
    public function testValidDate($date, $isValid): void
    {
        $result = Import::isValidDate($date);
        $this->assertEquals($result, $isValid);
    }

    public static function dateProvider()
    {
        return [
            ['02/01/2022', true],
            ['01/03/20', true],
            ['13/01/2020', false],
            ['12/32/2020', false],
            ['12/32/test', false],
            ['test', false]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("duplicateParticipantIDProvider")]
    public function testDuplicateParticipantId($imports, $participantId, $isDuplicate): void
    {
        $result = Import::hasDuplicateParticipantId($imports, $participantId);
        $this->assertEquals($result, $isDuplicate);
    }

    public static function duplicateParticipantIDProvider()
    {
        return [
            [[['participant_id' => 'P01'], ['participant_id' => 'P01'], ['participant_id' => 'P03']], 'P01', true],
            [[['participant_id' => 'P01'], ['participant_id' => 'P02'], ['participant_id' => 'P03']], 'P04', false]
        ];
    }

}
