<?php

use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\UserProvider;
use Pmi\Security\User;
use Symfony\Component\HttpFoundation\Request;
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
        $user = $auth->getUser($auth->getCredentials(new Request()), new UserProvider($this->app));
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
    }
    
    function testCheckCredentials()
    {
        $email = 'test2@testy.com';
        GoogleUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials(new Request()), new UserProvider($this->app));
        $this->assertEquals(true, $auth->checkCredentials($auth->getCredentials(new Request()), $user));
        
        GoogleUserService::clearCurrentUser();
        $user = new User(null, []);
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials(new Request()), $user));
        
        GoogleUserService::switchCurrentUser('happy@example.com');
        $user = new User(GoogleUserService::getCurrentUser(), $groups);
        GoogleUserService::switchCurrentUser('sad@example.com');
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials(new Request()), $user));
    }
}
