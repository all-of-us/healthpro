<?php
namespace Pmi\Drc;

use Pmi\Application\HpoApplication;

/**
 * Facilitates fixtures for unit tests and gaBypass to simulate the Google API.
 */
class MockAppsClient
{
    public static $groups = [];
    
    public static function createFromApp(HpoApplication $app)
    {
        return new MockAppsClient();
    }
    
    public function getGroups($userEmail = null)
    {
        return isset(self::$groups[$userEmail]) ? self::$groups[$userEmail] : [];
    }
    
    public static function setGroups($userEmail, $groups)
    {
        self::$groups[$userEmail] = $groups;
    }
}
