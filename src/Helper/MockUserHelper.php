<?php

namespace App\Helper;

use App\Security\MockUser;

class MockUserHelper
{
    private static ?MockUser $googleUser = null;

    public static function getCurrentUser(): ?MockUser
    {
        return self::$googleUser;
    }

    public static function switchCurrentUser(string $email, ?string $timezone = null): void
    {
        self::$googleUser = new MockUser($email, $timezone);
    }

    public static function clearCurrentUser(): void
    {
        self::$googleUser = null;
    }
}
