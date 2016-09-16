<?php

use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\UserProvider;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Tests\Pmi\GoogleUserService;
use Tests\Pmi\Drc\AppsClient;

class GoogleGroupsAuthenticatorTest extends AbstractWebTestCase
{
    function testGetUser()
    {
        $email = 'test1@testy.com';
        GoogleUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup('test-group3@gapps.com', 'Test Group 3', 'lorem ipsum 3')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser(null, new UserProvider($this->app));
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
    }
    
    function testCheckCredentials()
    {
        $email = 'test2@testy.com';
        GoogleUserService::switchCurrentUser($email);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser(null, new UserProvider($this->app));
        $this->assertEquals(true, $auth->checkCredentials(null, $user));
    }
}
