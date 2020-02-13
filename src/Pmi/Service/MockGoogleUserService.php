<?php

namespace Pmi\Service;

use Pmi\Security\MockGoogleUser;

/** Simulates GAE's UserService. */
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

    public static function createLogoutURL($destination_url)
    {
        return $destination_url;
    }
}
