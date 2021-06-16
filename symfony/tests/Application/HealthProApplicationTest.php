<?php

namespace App\Test\Application;

use App\Service\MockGoogleGroupsService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Tests\Pmi\GoogleGroup;

class HealthProApplicationTest extends WebTestCase
{
    private $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testController()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/s');
        self::assertMatchesRegularExpression('/\/s\/login$/', $this->client->getRequest()->getUri());
    }

    private function logIn($email, $groups)
    {
        $session = self::$container->get('session');
        $tokenStorage = self::$container->get('security.token_storage');
        $userService = self::$container->get('App\Service\UserService');
        $userProvider = self::$container->get('App\Security\UserProvider');

        $userService->setMockUser($email);
        if (!empty($groups)) {
            MockGoogleGroupsService::setGroups($email, $groups);
        }
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
        $this->logIn('testLogin@example.com', [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        $this->client->request('GET', '/s');
        self::assertMatchesRegularExpression('/\/s$/', $this->client->getRequest()->getUri());
    }
}
