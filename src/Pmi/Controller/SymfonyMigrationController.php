<?php
namespace Pmi\Controller;

use Silex\Application;

class SymfonyMigrationController extends AbstractController
{
    protected static $routes = [
        ['settings', '/settings', ['method' => 'GET|POST']],
    ];

    /**
     * @deprecated 2020-08-22
     */
    public function settingsAction(Application $app)
    {
        return $app->redirect('/s/settings');
    }
}
