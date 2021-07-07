<?php
use Pmi\Drc\MockAppsClient as AppsClient;
use Pmi\Security\User;
use Tests\Pmi\AbstractWebTestCase;
use Tests\Pmi\GoogleGroup;
use Pmi\Service\MockUserService;
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
        $client->followRedirects();
        $client->request('GET', '/');
        $this->assertEquals('/s/login', $client->getRequest()->getRequestUri());
    }

    public function testLogin()
    {
        $email = 'testLogin@example.com';
        MockUserService::switchCurrentUser($email);
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
        MockUserService::switchCurrentUser($email);
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
}
