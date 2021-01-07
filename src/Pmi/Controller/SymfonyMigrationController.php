<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class SymfonyMigrationController extends AbstractController
{
    protected static $routes = [
        ['settings', '/settings', ['method' => 'GET|POST']],
        ['deceased_reports_index', '/deceased-participant'],
        ['deceased_report_new', '/deceased-participant/{participantId}/new'],
        ['deceased_report_history', '/deceased-participant/{participantId}/history'],
        ['problem_reports', '/problem/reports'],
        ['problemForm', '/participant/{participantId}/problem/{problemId}', [
            'method' => 'GET|POST',
            'defaults' => ['problemId' => null]
        ]],
        ['admin_home', '/admin']
    ];

    public function deceased_reports_indexAction(Application $app)
    {
        return $app->redirect('/s/deceased-reports/');
    }

    public function deceased_report_newAction(Application $app, $participantId)
    {
        return $app->redirect('/s/deceased-reports/' . $participantId . '/new');
    }

    public function deceased_report_historyAction(Application $app, $participantId)
    {
        return $app->redirect('/s/deceased-reports/' . $participantId . '/history');
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

    /**
     * @deprecated 2020-12-21
     */
    public function problem_reportsAction(Application $app, Request $request)
    {
        return $app->redirect('/s/problem/reports/');
    }

    /**
     * @deprecated 2020-12-21
     */
    public function problemFormAction($participantId, $problemId, Application $app, Request $request)
    {
        if (!$problemId) {
            return $app->redirect(sprintf(
                '/s/participant/%s/problem',
                $participantId,
            ));
        }

        return $app->redirect(sprintf(
            '/s/participant/%s/problem/%d',
            $participantId,
            $problemId
        ));

    }

    /**
     * @deprecated 2020-12-23
     */
    public function admin_homeAction(Application $app)
    {
        return $app->redirect('/s/admin');
    }
}
