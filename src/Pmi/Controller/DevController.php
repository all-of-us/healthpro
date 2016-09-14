<?php
namespace Pmi\Controller;

use Pmi\Application\AbstractApplication as Application;
use Pmi\Entities\Configuration;
use Symfony\Component\HttpFoundation\Request;
use google\appengine\api\users\UserService;

class DevController extends AbstractController
{
    protected static $name = '_dev';

    protected static $routes = [
        ['datastoreInit', '/datastore-init']
    ];

    public function datastoreInitAction(Application $app, Request $request)
    {
        if ($app['env'] !== Application::ENV_DEV && $app['env'] !== Application::ENV_TEST) {
            return $app->abort(404);
        } elseif (!UserService::isCurrentUserAdmin()) {
            $app->addFlashError('Access denied!');
        } else {
            $keys = ['test', 'gaDomain', 'gaApplicationName', 'gaAuthJson', 'gaAdminEmail'];
            foreach ($keys as $key) {
                $value = $app->getConfig($key);
                if ($value === null) {
                    $config = new Configuration();
                    $config->setKey($key);
                    $config->setValue('');
                    $config->save();
                }
            }
            $app->addFlashNotice('Configuration initialized!');
        }
        return $app->redirectToRoute('home');
    }
}
