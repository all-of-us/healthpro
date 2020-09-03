<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class SymfonyMigrationController extends AbstractController
{
    protected static $routes = [
        ['settings', '/settings', ['method' => 'GET|POST']],
    ];

    /**
     * @deprecated 2020-08-21
     */
    public function settingsAction(Application $app, Request $request)
    {
        if ($request->query->get('return')) {
            return $app->redirect('/s/settings/?return=' . $request->query->get('return'));
        }
        return $app->redirect('/s/settings/');
    }
}
