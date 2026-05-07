<?php

namespace App\Service;

use App\Security\User;
use Google\Service\Directory\Group;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Facilitates fixtures for unit tests and gaBypass to simulate the Google API.
 */
class MockGoogleGroupsService
{
    /** @var array<string, list<Group>> */
    public static array $groups = [];
    private ParameterBagInterface $params;
    private EnvironmentService $env;

    public function __construct(ParameterBagInterface $params, EnvironmentService $env)
    {
        $this->params = $params;
        $this->env = $env;
    }

    /**
     * @return list<Group>
     */
    public function getGroups(?string $userEmail = null): array
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
                    new Group(['email' => User::ADMIN_GROUP . '@pmi-ops.io', 'name' => 'Site Admin'])
                ];
            }
            self::setGroups($userEmail, $groups);
        }

        if ($userEmail === null) {
            return [];
        }

        return isset(self::$groups[$userEmail]) ? self::$groups[$userEmail] : [];
    }

    public function getRole(string $userEmail, string $groupEmail): string
    {
        return 'MEMBER';
    }

    /**
     * @param list<Group> $groups
     */
    public static function setGroups(string $userEmail, array $groups): void
    {
        self::$groups[$userEmail] = $groups;
    }
}
