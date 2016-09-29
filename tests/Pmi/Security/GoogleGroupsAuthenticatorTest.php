<?php

use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\UserProvider;
use Pmi\Security\User;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Tests\Pmi\GoogleUserService;
use Tests\Pmi\Drc\AppsClient;

class GoogleGroupsAuthenticatorTest extends AbstractWebTestCase
{
    function testGetUser()
    {
        $email = 'test1@testy.com';
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup('test-group3@gapps.com', 'Test Group 3', 'lorem ipsum 3')
        ];
        AppsClient::setGroups($email, $groups);
        $user = $this->switchCurrentUser($email, true);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
    }
    
    function testCheckCredentials()
    {
        $email = 'test2@testy.com';
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $user = $this->switchCurrentUser($email, true);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertEquals(true, $auth->checkCredentials($auth->buildCredentials($this->app->getGoogleUser()), $user));
        
        $this->app->logout();
        $this->clearCurrentUser();
        $user = new User(null, []);
        $this->assertEquals(false, $auth->checkCredentials($auth->buildCredentials($this->app->getGoogleUser()), $user));
        
        $this->app->logout();
        $this->switchCurrentUser('happy@example.com');
        $user = new User($this->getCurrentUser(), $groups);
        $this->switchCurrentUser('sad@example.com', true);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertEquals(false, $auth->checkCredentials($auth->buildCredentials($this->app->getGoogleUser()), $user));
        $this->assertEquals(false, $auth->checkCredentials($auth->buildCredentials(null), $user));
        
        $this->app->logout();
        $email = 'no-groups@example.com';
        AppsClient::setGroups($email, []);
        $user = $this->switchCurrentUser($email, true);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
    }
    
    function testLogin()
    {
        $this->app->logout();
        $email = 'test3@testy.com';
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $user = $this->switchCurrentUser($email, true);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertSame(true, $this->app['session']->get('isLogin'));
        
        $this->app->logout();
        $this->assertSame(null, $this->app['session']->get('isLogin'));
    }
    
    function testGetCredentials()
    {
        $this->app->logout();
        $email = 'test4@testy.com';
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $user = $this->switchCurrentUser($email, true);
        $auth = new GoogleGroupsAuthenticator($this->app);
        
        // because we are now logged in, getCredentials() should be null
        $this->assertEquals(null, $auth->getCredentials($this->getRequest()));
        
        // likewise, we will have null when logged out because the credentials
        // are only available when returning from Google auth
        $this->app->logout();
        $this->assertEquals(null, $auth->getCredentials($this->getRequest()));
        
        
        $email = 'test5@testy.com';
        AppsClient::setGroups($email, $groups);
        $user = $this->switchCurrentUser($email, true);
        $auth = new GoogleGroupsAuthenticator($this->app);        
        // we want to fail if we are logged in and the google user changed
        $this->switchCurrentUser('rogue@hacker.com');
        $this->assertEquals($auth->buildCredentials(null), $auth->getCredentials($this->getRequest()));
    }
}
