<?php
namespace Tests\Pmi;

/** Simulates GAE's UserService. */
class GoogleUserService
{
    private static $googleUser;
    
    public static function getCurrentUser()
    {
        return self::$googleUser;
    }
    
    public static function switchCurrentUser($email)
    {
        self::$googleUser = new GoogleUser($email);
    }
    
    public static function clearCurrentUser()
    {
        self::$googleUser = null;
    }
    
    public static function createLoginURL($destination_url = null, $federated_identity = null)
    {
        return null;
    }
    
    public static function createLogoutURL($destination_url)
    {
        return null;
    }
}
