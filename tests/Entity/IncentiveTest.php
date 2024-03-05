<?php

namespace App\Tests\Entity;

use App\Entity\Incentive;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncentiveTest extends KernelTestCase
{
    /**
     * @dataProvider incentiveTypeProvider
     */
    public function testGetIncentiveTypeDisplayName(string $incentiveType, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveType($incentiveType);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveTypeDisplayName());
    }

    public function incentiveTypeProvider(): array
    {
        return [
            ['gift_card', 'Gift Card'],
            ['promotional', 'Promotional Item'],
            ['na', '']
        ];
    }

    /**
     * @dataProvider incentiveOccurrenceProvider
     */
    public function testGetIncentiveOccurrenceDisplayName(string $incentiveOccurrence, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveOccurrence($incentiveOccurrence);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveOccurrenceDisplayName());
    }

    public function incentiveOccurrenceProvider(): array
    {
        return [
            ['one_time', 'One-time Incentive'],
            ['redraw', 'Redraw'],
            ['na', '']
        ];
    }

    /**
     * @dataProvider incentiveAmountProvider
     */
    public function testGetIncentiveAmountDisplayName(string $incentiveAmount, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setIncentiveAmount($incentiveAmount);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveAmountDisplayName());
    }

    public function incentiveAmountProvider(): array
    {
        return [
            ['25', '$25.00'],
            ['15', '$15.00'],
            ['na', '']
        ];
    }

    /**
     * @dataProvider incentiveRecipientProvider
     */
    public function testGetIncentiveRecipientDisplayName(string $recipient, ?string $expectedDisplayName): void
    {
        $incentive = new Incentive();
        $incentive->setRecipient($recipient);

        $this->assertEquals($expectedDisplayName, $incentive->getIncentiveRecipientDisplayName());
    }

    public function incentiveRecipientProvider(): array
    {
        return [
            ['adult_participant', 'Adult Participant'],
            ['pediatric_guardian', 'Pediatric Guardian'],
            ['na', '']
        ];
    }
}
