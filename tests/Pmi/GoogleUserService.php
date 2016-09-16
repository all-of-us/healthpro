<?php
namespace Tests\Pmi;

/** Simulates GAE's UserService. */
class GoogleUserService
{
    private static $googleUser;
    
    public static function getCurrentUser()
    {
        if (!self::$googleUser) {
            self::$googleUser = new GoogleUser('unit-test@example.com');
        }
        return self::$googleUser;
    }
    
    public static function switchCurrentUser($email)
    {
        self::$googleUser = new GoogleUser($email);
    }
}
