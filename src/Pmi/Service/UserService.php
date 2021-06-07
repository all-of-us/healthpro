<?php

namespace Pmi\Service;

use Pmi\Security\User;

class UserService
{
    public static function getRoles($roles, $site, $awardee, $managegroups)
    {
        if (!empty($site)) {
            if (($key = array_search('ROLE_AWARDEE', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (($key = array_search('ROLE_AWARDEE_SCRIPPS', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (in_array($site->email, $managegroups)) {
                $roles[] = 'ROLE_MANAGE_USERS';
            } else {
                if (($key = array_search('ROLE_MANAGE_USERS', $roles)) !== false) {
                    unset($roles[$key]);
                }
            }
        }
        if (!empty($awardee)) {
            if (($key = array_search('ROLE_USER', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (isset($awardee->id) && $awardee->id !== User::AWARDEE_SCRIPPS && ($key = array_search('ROLE_AWARDEE_SCRIPPS', $roles)) !== false) {
                unset($roles[$key]);
            }
            if (($key = array_search('ROLE_MANAGE_USERS', $roles)) !== false) {
                unset($roles[$key]);
            }
        }
        return $roles;
    }

    public static function updateLastLogin($app): void
    {
        $user = $app->getUser();
        if (!$user) {
            return;
        }
        $app['em']->getRepository('users')->update(
            $user->getId(),
            ['last_login' => new \DateTime()]
        );
    }
}
