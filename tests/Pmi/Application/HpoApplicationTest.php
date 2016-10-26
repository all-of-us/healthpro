<?php
use Pmi\Drc\MockAppsClient as AppsClient;
use Pmi\Security\User;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Tests\Pmi\GoogleUserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HpoApplicationTest extends AbstractWebTestCase
{
    private $isLoginAfter;
    private $ipWhitelist = null;

    protected function afterCallback(Request $request, Response $response)
    {
        $this->isLoginAfter = $this->app['session']->get('isLogin');
    }

    public function testController()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testLogin()
    {
        $email = 'testLogin@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->assertSame(null, $this->app['session']->get('isLogin'));
        $client = $this->createClient();
        // should result in a successful login since the Google stuff is set
        $crawler = $client->request('GET', '/');
        // should still be true during the after callbacks
        $this->assertSame(true, $this->isLoginAfter);
        // gets set to false by the finishCallback()
        $this->assertSame(false, $this->app['session']->get('isLogin'));
    }

    public function testDashboardDeny()
    {
        $email = 'testDashboardDeny@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->assertSame(null, $this->app['session']->get('isLogin'));
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/dashboard/');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testDashboardAllow()
    {
        $email = 'testDashboardAllow@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('admin-dashboard@gapps.com', 'Admin Dashboard', 'lorem ipsum 1')]);
        $this->assertSame(null, $this->app['session']->get('isLogin'));
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/dashboard/');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testTimeout()
    {
        $email = 'testTimeout@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->app['sessionTimeout'] = 2;
        $client = $this->createClient();
        $client->followRedirects();
        $client->request('GET', '/');
        $client->request('POST', '/keepalive');
        $this->assertSame(false, $this->app->isLoginExpired());
        $this->assertEquals($email, $this->app->getUser()->getEmail());
        sleep($this->app['sessionTimeout']);
        $this->assertSame(true, $this->app->isLoginExpired());
    }
    
    public function testDashboardTimeout()
    {
        $email = 'testDashboardTimeout@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->app['sessionTimeout'] = 2;
        $client = $this->createClient();
        $client->followRedirects();
        $client->request('GET', '/');
        $client->request('POST', '/keepalive');
        $this->assertSame(false, $this->app->isLoginExpired());
        $this->assertEquals($email, $this->app->getUser()->getEmail());
        sleep($this->app['sessionTimeout']);
        $this->assertSame(true, $this->app->isLoginExpired());
    }
    
    public function testDashboardRedirect()
    {
        $email = 'testDashboardRedirect@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->app['sessionTimeout'] = 2;
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertSame(true, strstr($crawler->html(), '/dashboard/') !== false);
    }
    
    public function testForceSiteSelect()
    {
        $email = 'testForceSiteSelect@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'), new GoogleGroup('hpo-site-2@gapps.com', 'Test Group 2', 'lorem ipsum 2')]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/participants');
        $this->assertEquals(1, count($crawler->filter('#siteSelector')));
    }
    
    public function testDashSplash()
    {
        $email = 'testDashSplash@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/participants');
        $this->assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testUsageAgreement()
    {
        $email = 'testUsageAgreement@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')));
        $crawler = $client->reload();
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')));
        $client->request('POST', '/agree');
        $crawler = $client->request('GET', '/');
        $this->assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')));
    }
    
    function testGetIpWhitelist()
    {
        $this->app->setConfig('ip_whitelist', '');
        $this->assertEquals([], $this->app->getIpWhitelist());
        
        $this->app->setConfig('ip_whitelist', '127.0.0.1');
        $this->assertEquals(['127.0.0.1'], $this->app->getIpWhitelist());
        
        $this->app->setConfig('ip_whitelist', '  127.0.0.1, 8.8.8.8 ');
        $this->assertEquals(['127.0.0.1', '8.8.8.8'], $this->app->getIpWhitelist());
        
        $this->app->setConfig('ip_whitelist', '  127.0.0.1, 8.8.8.8 , 0.0.0.0');
        $this->assertEquals(['127.0.0.1', '8.8.8.8', '0.0.0.0'], $this->app->getIpWhitelist());
    }
    
    function testIpWhitelist0()
    {
        $this->ipWhitelist = '192.168.1.1';
        $this->app = $this->createApplication();
        $email = 'testIpWhitelist@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }
    
    function testIpWhitelist1()
    {
        $this->ipWhitelist = '192.168.1.1,8.8.8.8,127.0.0.1';
        $this->app = $this->createApplication();
        $email = 'testIpWhitelist@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $crawler = $client->request('GET', '/timeout');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
    
    protected function getIpWhitelist()
    {
        return $this->ipWhitelist;
    }
    
    public function testSiteAutoselect()
    {
        $email = 'testSiteAutoselect@example.com';
        GoogleUserService::switchCurrentUser($email);
        $groupEmail = 'hpo-site-1@gapps.com';
        AppsClient::setGroups($email, [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $this->assertSame(null, $this->app->getSite());
        $crawler = $client->request('GET', '/participants');
        $this->assertSame($groupEmail, $this->app->getSite()->email);
    }

    public function testHeaders()
    {
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $xframeOptions = $client->getResponse()->headers->get('X-Frame-Options');
        $this->assertSame('SAMEORIGIN', $xframeOptions);
    }
}
