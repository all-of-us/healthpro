<?php
namespace Tests\Pmi;

use Pmi\Application\HpoApplication;
use Pmi\Controller;
use Pmi\Security\GoogleGroupsAuthenticator;
use Pmi\Security\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractWebTestCase extends TestCase
{
    /**
     * HttpKernelInterface instance.
     *
     * @var HttpKernelInterface
     */
    protected $app;

    /**
     * PHPUnit setUp for setting up the application.
     *
     * Note: Child classes that define a setUp method must call
     * parent::setUp().
     */
    protected function setUp(): void
    {
        $this->app = $this->createApplication();
    }

    /**
     * Creates a Client.
     *
     * @param array $server Server parameters
     *
     * @return HttpKernelBrowser A HttpKernelBrowser instance
     */
    public function createClient(array $server = [])
    {
        if (!class_exists('Symfony\Component\BrowserKit\Client')) {
            throw new \LogicException('Component "symfony/browser-kit" is required by WebTestCase.'.PHP_EOL.'Run composer require symfony/browser-kit');
        }

        return new HttpKernelBrowser($this->app, $server);
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        putenv('PMI_ENV=' . HpoApplication::ENV_LOCAL);
        $app = new HpoApplication([
            'templatesDirectory' => __DIR__ . '/../../views',
            'webpackBuildDirectory' => __DIR__ . '/../../web/build',
            'errorTemplate' => 'error.html.twig',
            'isUnitTest' => true,
            'sessionTimeout' => 7 * 60,
            'sessionWarning' => 2 * 60
        ]);
        // session must be registered prior to boot()
        $app->register(new \Pmi\Session\SessionServiceProvider(), [
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
            'local_mock_auth' => true,
            'enforce2fa' => true
        ]);

        $app->mount('/', new Controller\DefaultController());
        $app->mount('/', new Controller\SymfonyMigrationController());
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
}
