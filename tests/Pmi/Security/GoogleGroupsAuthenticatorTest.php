<?php

use Pmi\Drc\MockAppsClient as AppsClient;
use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\UserProvider;
use Pmi\Security\User;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Pmi\Service\MockUserService;

class GoogleGroupsAuthenticatorTest extends AbstractWebTestCase
{
    function testGetUser()
    {
        $email = 'test1@testy.com';
        MockUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup('test-group3@gapps.com', 'Test Group 3', 'lorem ipsum 3')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
    }
    
    function testCheckCredentials()
    {
        $email = 'test2@testy.com';
        MockUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals(true, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        
        $this->app->logout();
        MockUserService::clearCurrentUser();
        $user = new User(null, []);
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        
        $this->app->logout();
        MockUserService::switchCurrentUser('happy@example.com');
        $user = new User(MockUserService::getCurrentUser(), $groups);
        MockUserService::switchCurrentUser('sad@example.com');
        $auth = new GoogleGroupsAuthenticator($this->app);
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        $this->assertEquals(false, $auth->checkCredentials($auth->buildCredentials(null), $user));
        
        $this->app->logout();
        $email = 'no-groups@example.com';
        MockUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, []);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        
        // main 2fa exception group
        $this->app->logout();
        $email = 'main-2fa@example.com';
        MockUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::TWOFACTOR_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        
        // site 2fa exception group
        $this->app->logout();
        $email = 'site-2fa@example.com';
        MockUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::TWOFACTOR_PREFIX . 'mysite@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
        
        // main and site 2fa exception groups
        $this->app->logout();
        $email = 'mainsite-2fa@example.com';
        MockUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::TWOFACTOR_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup(User::TWOFACTOR_PREFIX . 'mysite@gapps.com', 'Test Group 3', 'lorem ipsum 3')
        ]);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->assertEquals(false, $auth->checkCredentials($auth->getCredentials($this->getRequest()), $user));
    }
    
    function testLogin()
    {
        $this->app->logout();
        $email = 'test3@testy.com';
        MockUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->loginUser($auth, $user);
        $this->assertSame(true, $this->app['session']->get('isLogin'));
        
        $this->app->logout();
        $this->assertSame(null, $this->app['session']->get('isLogin'));
    }
    
    function testGetCredentials()
    {
        $this->app->logout();
        $email = 'test4@testy.com';
        MockUserService::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')
        ];
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->loginUser($auth, $user);
        
        // because we are now logged in, getCredentials() should be null
        $this->assertEquals(null, $auth->getCredentials($this->getRequest()));
        
        $this->app->logout();
        $this->assertEquals($email, $auth->getCredentials($this->getRequest())['googleUser']->getEmail());
        
        MockUserService::clearCurrentUser();
        $this->assertEquals(null, $auth->getCredentials($this->getRequest()));
        
        $email = 'test5@testy.com';
        MockUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, $groups);
        $auth = new GoogleGroupsAuthenticator($this->app);
        $user = $auth->getUser($auth->getCredentials($this->getRequest()), new UserProvider($this->app));
        $this->loginUser($auth, $user);
        
        // we want to fail if we are logged in and the google user changed
        MockUserService::switchCurrentUser('rogue@hacker.com');
        $this->assertEquals($auth->buildCredentials(null), $auth->getCredentials($this->getRequest()));
    }
}
