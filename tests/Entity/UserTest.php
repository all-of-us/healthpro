<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @dataProvider removeUsersDataProvider
     */
    public function testRemoveUserRoles($totalRoles, $removeRoles, $expectedNewRoles)
    {
        User::removeUserRoles($removeRoles, $totalRoles);
        $this->assertEmpty(array_diff($totalRoles, $expectedNewRoles));
    }

    public function removeUsersDataProvider(): array
    {
        return [
            [['ROLE_USER', 'ROLE_NPH_USER', 'ROLE_ADMIN'], ['ROLE_USER'], ['ROLE_NPH_USER', 'ROLE_ADMIN']],
            [['ROLE_USER', 'ROLE_NPH_USER', 'ROLE_ADMIN'], ['ROLE_USER', 'ROLE_NPH_USER'], ['ROLE_ADMIN']],
            [['ROLE_USER', 'ROLE_NPH_USER', 'ROLE_ADMIN'], ['ROLE_USER', 'ROLE_NPH_USER', 'ROLE_ADMIN'], []]
        ];
    }

    /**
     * @dataProvider timezoneDataProvider
     */
    public function testGetTimezoneId(string $timezone, int $expectedId)
    {
        $user = new User;
        $user->setTimezone($timezone);
        $this->assertEquals($expectedId, $user->getTimezoneId());
    }

    public static function timezoneDataProvider(): array
    {
        return [
            ['America/Puerto_Rico', 1],
            ['America/New_York', 2],
            ['America/Chicago', 3],
            ['America/Denver', 4],
            ['America/Phoenix', 5],
            ['America/Los_Angeles', 6],
            ['America/Anchorage', 7],
            ['Pacific/Honolulu', 8],
            ['Europe/London', 2],
            ['', 2],
            ['Unknown', 2],
        ];
    }
}
