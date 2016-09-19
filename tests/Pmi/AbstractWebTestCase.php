<?php
namespace tests\Pmi;

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
        putenv('PMI_ENV=' . HpoApplication::ENV_DEV);
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
        $testCase = $this;
        $app->after(function (Request $request, Response $response) use ($testCase) {
            $testCase->afterCallback($request, $response);
        });
        $app->setup();
        $app->mount('/', new Controller\DefaultController());
        
        return $app;
    }
    
    public function loginUser(GoogleGroupsAuthenticator $authenticator, User $user)
    {
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
    
    /** Children can override to access the after middleware. */
    protected function afterCallback(Request $request, Response $response) {}
}
