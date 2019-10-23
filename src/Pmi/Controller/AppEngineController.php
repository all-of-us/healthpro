<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppEngineController extends AbstractController
{
    protected static $name = 'gae';

    protected static $routes = [
        ['warmup', '/warmup']
    ];

    private function isAllowed(Application $app, Request $request)
    {
        // Internal requests like the warmup request will come from a 0.1.* IP address.
        // This is a secondary check, as GAE automatically protects the /_ah/ routes
        return strpos($request->getClientIp(), '0.1') === 0 || $app->isLocal();
    }

    public function warmupAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return new Response('Access denied', 403);
        }

        $app['logger']->info('Warmup request');

        $appDir = realpath(__DIR__ . '/../../..');
        $viewDir = $appDir . '/views';

        $finder = new Finder();
        foreach ($finder->files()->in($viewDir) as $file) {
            $app['logger']->info('Warming Twig cache: ' . $file->getRelativePathname());
            $app['twig']->loadTemplate($file->getRelativePathname());
        }

        // Load form template
        $app['logger']->info('Warming Twig cache: bootstrap_3_layout.html.twig');
        $app['twig']->loadTemplate('bootstrap_3_layout.html.twig');

        return new Response('Warmup complete.');
    }
}
