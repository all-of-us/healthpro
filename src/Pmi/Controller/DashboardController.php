<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';
    
    protected static $routes = [
        ['home', '/'],
        ['demo', '/demo']
    ];
    
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
