<?php

namespace Pmi\Service;

use Pmi\Security\MockGoogleUser;

class MockGoogleUserService
{
    private static $googleUser;

    public static function getCurrentUser()
    {
        return self::$googleUser;
    }

    public static function setMockUser($email)
    {
        self::$googleUser = new MockGoogleUser($email);
    }
}
