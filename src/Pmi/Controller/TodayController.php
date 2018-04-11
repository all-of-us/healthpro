<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class TodayController extends AbstractController
{
    protected static $name = 'today';

    protected static $routes = [
        ['home', '/']
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('today/index.html.twig');
    }
}
