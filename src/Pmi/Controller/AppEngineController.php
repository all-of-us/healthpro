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

    public function warmupAction(Application $app, Request $request)
    {
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

        $app['logger']->info('Headers: ' . print_r($request->headers->all(), 1));
        return new Response('Warmup complete.');
    }
}
