<?php
use Pmi\Drc\CodeBook;

class CodeBookTest extends \PHPUnit_Framework_TestCase
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
}
