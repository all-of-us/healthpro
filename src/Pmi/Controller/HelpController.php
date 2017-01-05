<?php
namespace Pmi\Controller;

use Silex\Application;

class HelpController extends AbstractController
{
    protected static $name = 'help';
    protected static $routes = [
        ['home', '/'],
        ['videos', '/videos'],
        ['faq', '/faq'],
        ['sop', '/sop']
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('help/index.html.twig');
    }

    public function videosAction(Application $app)
    {
        return $app['twig']->render('help/videos.html.twig');
    }

    public function faqAction(Application $app)
    {
        return $app['twig']->render('help/faq.html.twig');
    }

    public function sopAction(Application $app)
    {
        return $app['twig']->render('help/sop.html.twig');
    }
}
