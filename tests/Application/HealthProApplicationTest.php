<?php

namespace App\Tests\Application;

use App\Service\MockGoogleGroupsService;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use App\Tests\GoogleGroup;

class HealthProApplicationTest extends WebTestCase
{
    private $client;
    private $session;
    private $userService;
    private $env;

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
        $this->session = self::$container->get('session');
        $this->userService = self::$container->get('App\Service\UserService');
        $this->env = self::$container->get('App\Service\EnvironmentService');
        $tokenStorage = self::$container->get('security.token_storage');
        $userProvider = self::$container->get('App\Security\UserProvider');

        $this->userService->setMockUser($email);
        if (!empty($groups)) {
            MockGoogleGroupsService::setGroups($email, $groups);
        }
        $user = $userProvider->loadUserByUsername($email);
        $token = new PreAuthenticatedToken($user, null, 'main', $user->getRoles());
        $tokenStorage->setToken($token);

        $this->session->set('_security_main', serialize($token));
        $this->session->set('isLoginReturn', true);
        $this->session->save();

        $cookie = new Cookie($this->session->getName(), $this->session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    public function testLogin()
    {
        $this->logIn('testLogin@example.com', [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        $this->client->request('GET', '/s');
        self::assertEquals('/s/', $this->client->getRequest()->getRequestUri());
    }

    public function testUsageAgreement()
    {
        $this->logIn('testUsageAgreement@example.com', [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/s');
        self::assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on initial page load.');
        $crawler = $this->client->reload();
        self::assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on reload.');

        $this->client->request('POST', '/s/agree', ['csrf_token' => self::$container->get('security.csrf.token_manager')->getToken('agreeUsage')]);
        $crawler = $this->client->request('GET', '/s');
        self::assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')), 'Do not see usage modal after confirmation.');
    }

    public function testSiteAutoselect()
    {
        $groupEmail = 'hpo-site-1@gapps.com';
        $this->logIn('testSiteAutoselect@example.com', [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        self::assertSame(null, $this->session->get('site'));
        $this->client->request('GET', '/s/participants');
        self::assertSame($groupEmail, $this->session->get('site')->email);
    }

    public function testAwardeeAutoselect()
    {
        $groupEmail = 'awardee-1@gapps.com';
        $this->logIn('testAwardeeAutoselect@example.com', [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        self::assertSame(null, $this->session->get('awardee'));
        $this->client->request('GET', '/s');
        self::assertSame($groupEmail, $this->session->get('awardee')->email);
        self::assertEquals('/s/workqueue/', $this->client->getRequest()->getRequestUri());
    }

    public function testDvAdminAutoselect()
    {
        $groupEmail = User::ADMIN_DV . '@gapps.com';
        $this->logIn('testDvAdminAutoselect@example.com', [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        self::assertSame(null, $this->session->get('site'));
        $this->client->request('GET', '/s');
        self::assertEquals('/s/problem/reports', $this->client->getRequest()->getRequestUri());
    }

    public function testAdminAutoselect()
    {
        $groupEmail = User::ADMIN_GROUP . '@gapps.com';
        $this->logIn('testAdminAutoselect@example.com', [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $this->client->followRedirects();
        self::assertSame(null, $this->session->get('site'));
        $this->client->request('GET', '/s');
        self::assertEquals('/s/admin', $this->client->getRequest()->getRequestUri());
    }

    public function testForceSiteSelect()
    {
        $this->logIn('testForceSiteSelect@example.com', [
            new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup('hpo-site-2@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $this->client->followRedirects();
        $this->client->request('GET', '/s/participants');
        self::assertMatchesRegularExpression('/\/s\/site\/select$/', $this->client->getRequest()->getUri());
    }

    public function testHeaders()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/s');
        $xframeOptions = $this->client->getResponse()->headers->get('X-Frame-Options');
        self::assertSame('SAMEORIGIN', $xframeOptions);
    }
}
