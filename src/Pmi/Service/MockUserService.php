<?php

namespace Pmi\Service;

use Pmi\Security\MockUser;

class MockUserService
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
