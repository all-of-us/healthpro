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
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on initial page load.');
        $crawler = $this->client->reload();
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')), 'See usage modal on reload.');
        $csrfToken = static::getContainer()->get('security.csrf.token_manager')->getToken('agreeUsage')->getValue();

        $this->client->request('POST', '/agree', ['csrf_token' => $csrfToken]);
        $this->assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/');
        $this->assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')), 'Do not see usage modal after confirmation.');
    }

    public function testAdminAutoselect()
    {
        $this->login('testAdminAutoselect@example.com', [User::ADMIN_GROUP]);
        $this->client->followRedirects();
        $this->assertNull($this->session->get('site'));
        $this->client->request('GET', '/');
        $this->assertEquals('/admin', $this->client->getRequest()->getRequestUri());
    }

    public function testHeaders()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/');
        $this->assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN');
    }
}
