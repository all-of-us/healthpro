<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/']
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('index.html.twig');
    }
}
