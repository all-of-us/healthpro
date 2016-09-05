<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class GoogleAppsController extends AbstractController
{
    protected static $name = 'googleapps';
    
    protected static $routes = [
        ['home', '/']
    ];
    
    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('googleapps.html.twig');
    }
}
