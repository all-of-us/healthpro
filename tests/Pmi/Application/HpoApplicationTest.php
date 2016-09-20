<?php
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Tests\Pmi\GoogleUserService;
use Tests\Pmi\Drc\AppsClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HpoApplicationTest extends AbstractWebTestCase
{
    private $isLoginAfter;
    
    protected function afterCallback(Request $request, Response $response) {
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
    
    public function testTimeout()
    {
        $email = 'testTimeout@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $this->app['sessionTimeout'] = 2;
        $client = $this->createClient();
        $client->request('POST', '/keepalive');
        $this->assertSame(false, $this->app->isLoginExpired());
        $this->assertEquals($email, $this->app->getUser()->getEmail());
        sleep($this->app['sessionTimeout']);
        $this->assertSame(true, $this->app->isLoginExpired());
    }

    public function testUsageAgreement()
    {
        $email = 'testUsageAgreement@example.com';
        GoogleUserService::switchCurrentUser($email);
        AppsClient::setGroups($email, [new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1')]);
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')));
        $crawler = $client->reload();
        $this->assertEquals(1, count($crawler->filter('#pmiSystemUsageTpl')));
        $crawler = $client->request('POST', '/agree');
        $this->assertEquals(0, count($crawler->filter('#pmiSystemUsageTpl')));
    }
}
