<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use App\Util;

class UtilTest extends WebTestCase
{
    public function testUuid()
    {
        $uuid = Util::generateUuid();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid);
    }

    public function testShortUuid()
    {
        $this->assertMatchesRegularExpression('/^[0-9A-F]{16}$/', Util::generateShortUuid());
        $this->assertMatchesRegularExpression('/^[0-9A-F]{24}$/', Util::generateShortUuid(24));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Util::generateShortUuid(32, false));
    }

    public function testVersionIsAtLeast()
    {
        $this->assertTrue(Util::versionIsAtLeast('0.1.2', '0.1.1'));
        $this->assertTrue(Util::versionIsAtLeast('0.1.2', '0.1'));
        $this->assertTrue(Util::versionIsAtLeast('1.1.2', '1.1.2'));
        $this->assertTrue(Util::versionIsAtLeast('3.0', '2.1.9'));
        $this->assertTrue(Util::versionIsAtLeast('4.5.3.1', '4.0.91'));

        $this->assertFalse(Util::versionIsAtLeast('0.1.2', '0.1.3'));
        $this->assertFalse(Util::versionIsAtLeast('1.1.2', '1.2'));
        $this->assertFalse(Util::versionIsAtLeast('1.1.2', '2.0.0'));
        $this->assertFalse(Util::versionIsAtLeast('1.1.2', '1.1.12'));
    }
}
