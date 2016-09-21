<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Drc\RdrMetrics;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';
    
    protected static $routes = [
        ['home', '/'],
        ['metrics_load', '/metrics_load'],
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }

    public function metrics_loadAction(Application $app, Request $request)
    {
        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        // load attribute to query
        $metrics_attribute = $request->get('metrics_attribute');
        $result = $metricsApi->metrics($metrics_attribute)->bucket;
        return $app->json($result);
    }

}
