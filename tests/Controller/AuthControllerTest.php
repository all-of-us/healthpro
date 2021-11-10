<?php

namespace App\Tests\Controller;

use App\Security\User;
use App\Tests\GoogleGroup;

class AuthControllerTest extends AppWebTestCase
{
    public function testController()
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/');
        $this->assertMatchesRegularExpression('/\/login$/', $this->client->getRequest()->getUri());
    }

    public function testLogin()
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

        $this->client->request('POST', '/agree', ['csrf_token' => self::$container->get('security.csrf.token_manager')->getToken('agreeUsage')]);
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

    public function testAwardeeAutoselect()
    {
        $awardeeId = 'awardee-' . uniqid();
        $this->login('testAwardeeAutoselect@example.com', [$awardeeId]);
        $this->client->followRedirects();
        $this->assertNull($this->session->get('awardee'));
        $this->client->request('GET', '/');
        $this->assertSame($awardeeId . '@' . static::GROUP_DOMAIN, $this->session->get('awardee')->email);
        $this->assertEquals('/workqueue/', $this->client->getRequest()->getRequestUri());
    }

    public function testDvAdminAutoselect()
    {
        $this->login('testDvAdminAutoselect@example.com', [User::ADMIN_DV]);
        $this->client->followRedirects();
        $this->assertNull($this->session->get('site'));
        $this->client->request('GET', '/');
        $this->assertEquals('/problem/reports', $this->client->getRequest()->getRequestUri());
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
        $xframeOptions = $this->client->getResponse()->headers->get('X-Frame-Options');
        $this->assertSame('SAMEORIGIN', $xframeOptions);
    }
}
