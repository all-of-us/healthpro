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
        ['demo', '/demo'],
        ['apitest', '/apitest']
    ];

    public function apitestAction(Application $app, Request $request)
    {
        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        $result = $metricsApi->metrics('PARTICIPANT_TOTAL');
        return $app->json($result->bucket);
    }

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }

    public function demoAction(Application $app, Request $request)
    {
        $rows = $app['db']->fetchAll('SELECT state, value FROM demo order by state');
        $states = [];
        foreach ($rows as $row) {
            $states[$row['state']] = (int)$row['value'];
        }
        return $app['twig']->render('dashboard/demo.html.twig', [
            'states' => $states
        ]);
    }
}
