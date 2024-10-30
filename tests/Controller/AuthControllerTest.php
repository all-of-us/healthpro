<?php

namespace App\Tests\Controller;

use App\Security\User;
use App\Tests\GoogleGroup;

class AuthControllerTest extends AppWebTestCase
{
    public function testUnauthenticatedRedirectToLogin()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/');
        $this->assertSame('/login', $this->client->getRequest()->getRequestUri());
    }

    public function testAuthenticatedDashboard()
    {
        $this->login('testLogin@example.com');
        $this->client->followRedirects();
        $this->client->request('GET', '/');
        $this->assertEquals('/', $this->client->getRequest()->getRequestUri());
    }

    public function testUsageAgreement()
    {
        $this->login('testUsageAgreement@example.com');
        // Should set this immediately after login to prevent token mismatch issues
        $csrfToken = self::$container->get('security.csrf.token_manager')->getToken('agreeUsage');
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on initial page load.');
        $crawler = $this->client->reload();
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on reload.');

        $this->client->request('POST', '/agree', ['csrf_token' => $csrfToken]);
        $crawler = $this->client->request('GET', '/');
        $this->assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')), 'Do not see usage modal after confirmation.');
    }

    public function testSiteAutoselect()
    {
        $siteId = 'hpo-site-' . uniqid();
        $this->login('testSiteAutoselect@example.com', [$siteId]);
        $this->client->followRedirects();
        $this->assertNull($this->session->get('site'));
        $this->client->request('GET', '/participants');
        $this->assertSame($siteId . '@' . static::GROUP_DOMAIN, $this->session->get('site')->email);
    }

    public function testAdminAutoselect()
    {
        $this->login('testAdminAutoselect@example.com', [User::ADMIN_GROUP]);
        $this->client->followRedirects();
        $this->assertNull($this->session->get('site'));
        $this->client->request('GET', '/');
        $this->assertEquals('/admin', $this->client->getRequest()->getRequestUri());
    }

    public function testForceSiteSelect()
    {
        $siteIds = [
            'hpo-site-' . uniqid(),
            'hpo-site-' . uniqid()
        ];
        $this->login('testForceSiteSelect@example.com', $siteIds);
        $this->client->followRedirects();
        $this->client->request('GET', '/participants');
        $this->assertMatchesRegularExpression('/\/site\/select$/', $this->client->getRequest()->getUri());
    }

    public function testHeaders()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/');
        $this->assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN');
    }

    public function testSiteUserReadOnlyRoutes()
    {
        $siteId = 'hpo-site-' . uniqid();
        $this->login('testSiteUserReadOnlyRoutes@example.com', [$siteId]);
        $this->client->followRedirects();

        $this->client->request('GET', '/read/');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/read/participants');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/read/orders');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminUserReadOnlyRoutes()
    {
        $this->login('testAdminUserReadOnlyRoutes@example.com', [User::ADMIN_GROUP]);
        $this->client->followRedirects();

        $this->client->request('GET', '/read/');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/read/participants');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/read/orders');
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }
}
