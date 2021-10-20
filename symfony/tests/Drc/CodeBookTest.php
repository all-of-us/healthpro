<?php

namespace App\Tests\CodeBook;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use App\Drc\CodeBook;

class CodeBookTest extends WebTestCase
{
    public function testCodeBook()
    {
        // No transformation if not in code book
        $this->assertSame('foo', CodeBook::display('foo'));
        $this->assertSame(10, CodeBook::display(10));

        // Transform if in code book
        $this->assertSame('French (Switzerland)', CodeBook::display('SpokenWrittenLanguage_FrenchSwitzerland'));
        $this->assertSame('French', CodeBook::display('SpokenWrittenLanguage_French'));
        $this->assertSame('', CodeBook::display('UNSET'));
        $this->assertSame('Woman', CodeBook::display('GenderIdentity_Woman'));
    }

    public function testStates()
    {
        $this->assertSame('TN', CodeBook::display('PIIState_TN'));
    }

    public function testAgeRangeConversion()
    {
        $ten = (new \DateTime('-10 years'))->format('Y-m-d');
        $twentyOne = (new \DateTime('-21 years'))->format('Y-m-d');
        $eighty = (new \DateTime('-80 years'))->format('Y-m-d');

        $this->assertSame(["le{$ten}", "gt{$twentyOne}"], CodeBook::ageRangeToDob('10-20'));
        $this->assertSame(["le{$eighty}"], CodeBook::ageRangeToDob('80-'));
    }
}
