<?php

namespace App\Service;

use Pmi\Security\User;
use \Google_Service_Directory_Group as Group;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Facilitates fixtures for unit tests and gaBypass to simulate the Google API.
 */
class MockGoogleGroupsService
{
    private $params;
    private $env;

    public static $groups = [];

    public function __construct(ParameterBagInterface $params, EnvironmentService $env)
    {
        $this->params = $params;
        $this->env = $env;
    }

    public function getGroups($userEmail = null)
    {
        // use fixtures when using gaBypass in dev
        if ($userEmail && !$this->env->values['isUnitTest'] && empty(self::$groups[$userEmail])) {
            if ($this->params->has('gaBypassGroups') && is_array($this->params->get('gaBypassGroups'))) {
                $groups = [];
                foreach ($this->params->get('gaBypassGroups') as $arr) {
                    $groups[] = new Group($arr);
                }

            } else {
                $groups = [
                    new Group(['email' => User::SITE_PREFIX . 'hogwarts@pmi-ops.io', 'name' => 'Hogwarts']),
                    new Group(['email' => User::SITE_PREFIX . 'durmstrang@pmi-ops.io', 'name' => 'Durmstrang']),
                    new Group(['email' => User::SITE_PREFIX . 'beauxbatons@pmi-ops.io', 'name' => 'Beauxbatons']),
                    new Group(['email' => User::DASHBOARD_GROUP . '@pmi-ops.io', 'name' => 'Admin Dashboard']),
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
