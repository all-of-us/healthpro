<?php

namespace App\Tests\Entity;

use App\Entity\Incentive;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncentiveTest extends KernelTestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider("incentiveTypeProvider")]
    public function testGetIncentiveTypeDisplayName(string $incentiveType, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveType($incentiveType);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveTypeDisplayName());
    }

    public static function incentiveTypeProvider(): array
    {
        return [
            ['gift_card', 'Gift Card'],
            ['promotional', 'Promotional Item'],
            ['na', '']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("incentiveOccurrenceProvider")]
    public function testGetIncentiveOccurrenceDisplayName(string $incentiveOccurrence, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveOccurrence($incentiveOccurrence);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveOccurrenceDisplayName());
    }

    public static function incentiveOccurrenceProvider(): array
    {
        return [
            ['one_time', 'One-time Incentive'],
            ['redraw', 'Redraw'],
            ['na', '']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("incentiveAmountProvider")]
    public function testGetIncentiveAmountDisplayName(string $incentiveAmount, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveAmount($incentiveAmount);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveAmountDisplayName());
    }

    public static function incentiveAmountProvider(): array
    {
        return [
            ['25', '$25.00'],
            ['15', '$15.00'],
            ['na', '']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("incentiveRecipientProvider")]
    public function testGetIncentiveRecipientDisplayName(string $recipient, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setRecipient($recipient);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveRecipientDisplayName());
    }

    public static function incentiveRecipientProvider(): array
    {
        return [
            ['adult_participant', 'Adult Participant'],
            ['pediatric_guardian', 'Pediatric Guardian'],
            ['other, text1', 'Other'],
            ['na', '']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("incentiveOtherRecipientProvider")]
    public function testGetOtherIncentiveRecipient(string $recipient, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setRecipient($recipient);

        $this->assertEquals($expectedDisplayName, $incentive->getOtherIncentiveRecipient());
    }

    public static function incentiveOtherRecipientProvider(): array
    {
        return [
            ['other, text1', 'text1'],
            ['other, text1 text2', 'text1 text2'],
            ['other, text1, text2', 'text1, text2'],
            ['na', null]
        ];
    }
}
