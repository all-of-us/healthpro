<?php
namespace Tests\Pmi;

use Pmi\Application\HpoApplication;
use Pmi\Controller;
use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\User;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractWebTestCase extends WebTestCase
{
    /** http://silex.sensiolabs.org/doc/master/testing.html#webtestcase */
    public function createApplication()
    {
        putenv('PMI_ENV=' . HpoApplication::ENV_LOCAL);
        $app = new HpoApplication([
            'templatesDirectory' => __DIR__ . '/../../views',
            'errorTemplate' => 'error.html.twig',
            'isUnitTest' => true,
            'sessionTimeout' => 7 * 60,
            'sessionWarning' => 2 * 60
        ]);
        // session must be registered prior to boot()
        $app->register(new \Silex\Provider\SessionServiceProvider(), [
            'session.test' => true
        ]);
        $app['session.storage.test'] = function () {
            return new MockFileSessionStorage();
        };
        $testCase = $this;
        $app->after(function (Request $request, Response $response) use ($testCase) {
            $testCase->afterCallback($request, $response);
        });
        $app->setup([
            // don't bypass groups auth because we handle this with fixtures
            'gaBypass' => false,
            'gaDomain' => 'pmi-drc-hpo-unit-tests.biz',
            'ip_whitelist' => $this->getIpWhitelist(),
            'gae_auth' => true
        ]);
        $app->mount('/', new Controller\DefaultController());
        $app->mount('/dashboard', new Controller\DashboardController());
        
        return $app;
    }
    
    public function loginUser(GoogleGroupsAuthenticator $authenticator, User $user)
    {
        // hack so that authenticator won't crash building routes
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');
        
        $providerKey = 'main';
        $token = $authenticator->createAuthenticatedToken($user, $providerKey);
        $this->app['security.token_storage']->setToken($token);
        $authenticator->onAuthenticationSuccess($this->getRequest(), $token, $providerKey);
    }
    
    public function getRequest()
    {
        $request = new Request();
        $request->setSession($this->app['session']);
        return $request;
    }
    
    /** Override to access the after middleware. */
    protected function afterCallback(Request $request, Response $response) {}
    
    /** Override to specify IP whitelist. */
    protected function getIpWhitelist()
    {
        return null;
    }
}
