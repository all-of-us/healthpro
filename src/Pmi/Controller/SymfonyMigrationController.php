<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class SymfonyMigrationController extends AbstractController
{
    protected static $routes = [
        ['settings', '/settings', ['method' => 'GET|POST']],
        ['deceased_reports_index', '/deceased-participant'],
        ['deceased_report_new', '/deceased-participant/{participantId}/new']
    ];

    public function deceased_reports_indexAction(Application $app)
    {
        return $app->redirect('/s/deceased-reports/');
    }

    public function deceased_report_newAction(Application $app, $participantId)
    {
        return $app->redirect('/s/deceased-reports/' . $participantId . '/new');
    }

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
