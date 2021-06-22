<?php

namespace App\Test\Application;

use App\Service\MockGoogleGroupsService;
use Pmi\Security\User;
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
        $session->set('isLoginReturn', true);
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

    public function testDashSplash()
    {
        $this->logIn('testDashSplash@example.com', [
            new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/s/participants');
        self::assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testDashSplashAwardee()
    {
        $this->logIn('testDashSplashAwardee@example.com', [
            new GoogleGroup('awardee-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/s');
        self::assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testDashSplashAdmin()
    {
        $this->logIn('testDashSplashAdmin@example.com', [
            new GoogleGroup(User::ADMIN_GROUP . '@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/s');
        self::assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testDashSplashDvAdmin()
    {
        $this->logIn('testDashSplashDvAdmin@example.com', [
            new GoogleGroup(User::ADMIN_DV . '@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/s');
        self::assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }
}
