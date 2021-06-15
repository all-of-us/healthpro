<?php

namespace App\Test\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;

class HealthProApplicationTest extends WebTestCase
{
    private $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testController()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/s');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    private function logIn($email)
    {
        $session = self::$container->get('session');
        $tokenStorage = self::$container->get('security.token_storage');
        $userService = self::$container->get('App\Service\UserService');
        $userProvider = self::$container->get('App\Security\UserProvider');

        $userService->setMockUser($email);
        $user = $userProvider->loadUserByUsername($email);
        $token = new PreAuthenticatedToken($user, null, 'main', $user->getRoles());
        $tokenStorage->setToken($token);

        $session->set('_security_main', serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    public function testLogin()
    {
        $this->logIn('testLogin@example.com');
        $this->client->followRedirects();
        $this->client->request('GET', '/s');
        self::assertMatchesRegularExpression('/\/site\/select$/', $this->client->getRequest()->getUri());
    }
}
