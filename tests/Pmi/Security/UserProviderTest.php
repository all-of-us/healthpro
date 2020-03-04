<?php
use Pmi\Security\UserProvider;
use Tests\Pmi\AbstractWebTestCase;
use Pmi\Service\MockUserService;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserProviderTest extends AbstractWebTestCase
{
    public function testLoadUserByUsername()
    {
        $email = 'test@testLoadUserByUsername.com';
        MockUserService::switchCurrentUser($email);
        $provider = new UserProvider($this->app);
        $user = $provider->loadUserByUsername($email);
        $this->assertEquals($email, $user->getEmail());
        
        // test case compare
        $user = $provider->loadUserByUsername(strtoupper($email));
        $this->assertEquals($email, $user->getEmail());
    }
    
    public function testNoGoogleUser()
    {
        $email = 'test@testNoGoogleUser.com';
        MockUserService::clearCurrentUser();
        $provider = new UserProvider($this->app);
        $caught = false; // because we don't have expectException
        try {
            $provider->loadUserByUsername(strtoupper($email));
        } catch (AuthenticationException $e) {
            $caught = true;
        }
        $this->assertEquals(true, $caught);
    }
}
