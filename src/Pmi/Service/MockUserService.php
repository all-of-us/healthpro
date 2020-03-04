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

    public static function switchCurrentUser($email)
    {
        self::$googleUser = new MockUser($email);
    }

    public static function clearCurrentUser()
    {
        self::$googleUser = null;
    }
}
