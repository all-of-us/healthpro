<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';
    
    protected static $routes = [
        ['home', '/']
    ];
    
    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }
}
