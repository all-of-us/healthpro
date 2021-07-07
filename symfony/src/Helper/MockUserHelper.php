<?php

namespace App\Helper;

use App\Security\MockUser;

class MockUserHelper
{
    private static $googleUser;

    public static function getCurrentUser()
    {
        return self::$googleUser;
    }

    public static function switchCurrentUser($email, $timezone = null)
    {
        self::$googleUser = new MockUser($email, $timezone);
    }

    public static function clearCurrentUser()
    {
        self::$googleUser = null;
    }
}
