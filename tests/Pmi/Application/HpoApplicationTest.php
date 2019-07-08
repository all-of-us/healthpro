<?php
use Pmi\Drc\MockAppsClient as AppsClient;
use Pmi\Security\User;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Tests\Pmi\GoogleUserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;

class HpoApplicationTest extends AbstractWebTestCase
{
    private $isLoginAfter;

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

    public function testTwoFactorDeny()
    {
        $email = 'testTwoFactorDeny@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::TWOFACTOR_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertSame(1, count($crawler->filter('#twoFactorAlert')));
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testDashboardDeny()
    {
        $email = 'testDashboardDeny@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('hpo-site-1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->assertSame(null, $this->app['session']->get('isLogin'));
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/dashboard');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
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
        $crawler = $client->request('GET', '/dashboard');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
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
        $client->request('POST', '/keepalive', ['csrf_token' => $this->app['csrf.token_manager']->getToken('keepAlive')]);
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
        $client->request('POST', '/keepalive', ['csrf_token' => $this->app['csrf.token_manager']->getToken('keepAlive')]);
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

    public function testDashSplashAwardee()
    {
        $email = 'testDashSplashAwardee@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup('awardee-1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testDashSplashAdmin()
    {
        $email = 'testDashSplashAdmin@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup(User::ADMIN_GROUP . '@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#dashSplashSelector')));
    }

    public function testDashSplashDvAdmin()
    {
        $email = 'testDashSplashDvAdmin@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [
            new GoogleGroup(User::ADMIN_DV . '@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::DASHBOARD_GROUP . '@gapps.com', 'Test Group 2', 'lorem ipsum 2')
        ]);
        $client = $this->createClient();
        $client->followRedirects();
        $crawler = $client->request('GET', '/');
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
        $client->request('POST', '/agree', ['csrf_token' => $this->app['csrf.token_manager']->getToken('agreeUsage')]);
        $crawler = $client->request('GET', '/');
        $this->assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')));
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

    public function testAwardeeAutoselect()
    {
        $email = 'testAwardeeAutoselect@example.com';
        GoogleUserService::switchCurrentUser($email);
        $groupEmail = 'awardee-1@gapps.com';
        AppsClient::setGroups($email, [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $this->assertSame(null, $this->app->getSite());
        $crawler = $client->request('GET', '/workqueue');
        $this->assertSame($groupEmail, $this->app->getAwardee()->email);
    }

    public function testDvAdminAutoselect()
    {
        $email = 'testDvAdminAutoselect@example.com';
        GoogleUserService::switchCurrentUser($email);
        $groupEmail = User::ADMIN_DV . '@gapps.com';
        AppsClient::setGroups($email, [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $this->assertSame(null, $this->app->getSite());
        $crawler = $client->request('GET', '/problem/reports');
        $this->assertEquals(1, count($crawler->filter('#problem_reports')));
    }

    public function testAdminAutoselect()
    {
        $email = 'testAdminAutoselect@example.com';
        GoogleUserService::switchCurrentUser($email);
        $groupEmail = User::ADMIN_GROUP . '@gapps.com';
        AppsClient::setGroups($email, [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $this->assertSame(null, $this->app->getSite());
        $crawler = $client->request('GET', '/admin');
        $this->assertEquals(1, count($crawler->filterXPath('//a[@href="/admin/sites"]')));
    }

    public function testDashboardAutoselect()
    {
        $email = 'testDashboardAutoselect@example.com';
        GoogleUserService::switchCurrentUser($email);
        $groupEmail = User::DASHBOARD_GROUP . '@gapps.com';
        AppsClient::setGroups($email, [new GoogleGroup($groupEmail, 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $client->followRedirects();
        $this->assertSame(null, $this->app->getSite());
        $crawler = $client->request('GET', '/dashboard');
        $this->assertEquals(1, count($crawler->filter('#plotly-total-progress')));
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
