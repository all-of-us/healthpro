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
}
