<?php
namespace Tests\Pmi\Drc;

/** Provides fixtures for unit tests to simulate the Google API. */
class AppsClient
{
    public static $groups = [];
    
    public function getGroups($userEmail = null)
    {
        return isset(self::$groups[$userEmail]) ? self::$groups[$userEmail] : [];
    }
    
    public static function setGroups($userEmail, $groups)
    {
        self::$groups[$userEmail] = $groups;
    }
}
