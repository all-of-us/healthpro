<?php
namespace Pmi\Drc;

use Pmi\Application\HpoApplication;
use Pmi\Security\User;
use \Google_Service_Directory_Group as Group;

/**
 * Facilitates fixtures for unit tests and gaBypass to simulate the Google API.
 */
class MockAppsClient
{
    public static $groups = [];
    public static $app;
    
    public static function createFromApp(HpoApplication $app)
    {
        if ($app->isProd()) { // for sanity's sake
            throw new \Exception("Cannot create MockAppsClient in production!");
        }
        self::$app = $app;
        return new MockAppsClient();
    }
    
    public function getGroups($userEmail = null)
    {
        // when using gaBypass in dev, use fixtures
        if ($userEmail && !self::$app['isUnitTest'] && empty(self::$groups[$userEmail])) {
            self::setGroups($userEmail, [
                new Group(['email' => User::SITE_PREFIX . 'hogwarts@pmi-ops.io', 'name' => 'Hogwarts']),
                new Group(['email' => User::SITE_PREFIX . 'durmstrang@pmi-ops.io', 'name' => 'Durmstrang']),
                new Group(['email' => User::SITE_PREFIX . 'beauxbatons@pmi-ops.io', 'name' => 'Beauxbatons']),
                new Group(['email' => User::DASHBOARD_GROUP . '@pmi-ops.io', 'name' => 'Admin Dashboard'])
            ]);
        }
        
        return isset(self::$groups[$userEmail]) ? self::$groups[$userEmail] : [];
    }
    
    public static function setGroups($userEmail, $groups)
    {
        self::$groups[$userEmail] = $groups;
    }
}
