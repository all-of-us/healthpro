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
        // use fixtures when using gaBypass in dev
        if ($userEmail && !self::$app['isUnitTest'] && empty(self::$groups[$userEmail])) {
            if (is_array(self::$app->getConfig('gaBypassGroups'))) {
                $groups = [];
                foreach (self::$app->getConfig('gaBypassGroups') as $arr) {
                    $groups[] = new Group($arr);
                }

            } else {
                $groups = [
                    new Group(['email' => User::SITE_PREFIX . 'hogwarts@pmi-ops.io', 'name' => 'Hogwarts']),
                    new Group(['email' => User::SITE_PREFIX . 'durmstrang@pmi-ops.io', 'name' => 'Durmstrang']),
                    new Group(['email' => User::SITE_PREFIX . 'beauxbatons@pmi-ops.io', 'name' => 'Beauxbatons']),
                    new Group(['email' => User::ADMIN_GROUP . '@pmi-ops.io', 'name' => 'Site Admin'])
                ];
            }
            self::setGroups($userEmail, $groups);
        }

        return isset(self::$groups[$userEmail]) ? self::$groups[$userEmail] : [];
    }

    public function getRole($userEmail, $groupEmail)
    {
        return 'MEMBER';
    }

    public static function setGroups($userEmail, $groups)
    {
        self::$groups[$userEmail] = $groups;
    }
}
