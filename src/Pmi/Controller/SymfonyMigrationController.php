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
        ['admin_home', '/admin'],
        ['review_today', '/review'],
        ['orderCheck', '/participant/{participantId}/order/check'],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['workqueue_index', '/workqueue', ['method' => 'GET|POST']],
        ['workqueue_participant', '/workqueue/participant/{id}'],
        ['participant', '/participant/{id}', ['method' => 'GET|POST']],
        ['participants', '/participants'],
        ['biobank_home', '/biobank']
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
                $participantId
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

    /**
     * @deprecated 2021-01-20
     */
    public function review_todayAction(Application $app)
    {
       return $app->redirect('/s/review/');
    }

    /**
     * @deprecated 2021-01-21
     */
    public function orderCheckAction($participantId, Application $app)
    {
        return $app->redirect(sprintf(
            '/s/participant/%s/order/check',
            $participantId
        ));
    }

    /**
     * @deprecated 2021-01-21
     */
    public function orderAction($participantId, $orderId, Application $app)
    {
        return $app->redirect(sprintf(
            '/s/participant/%s/order/%d',
            $participantId,
            $orderId
        ));
    }


    /**
     * @deprecated 2021-02-08
     */
    public function workqueue_indexAction(Application $app)
    {
        return $app->redirect('/s/workqueue/');
    }

    /**
     * @deprecated 2021-02-18
     */
    public function workqueue_participantAction($id, Application $app)
    {
        return $app->redirect(sprintf(
            '/s/workqueue/participant/%s',
            $id
        ));
    }

    /**
     * @deprecated 2021-02-18
     */
    public function participantAction($id, Application $app)
    {
        return $app->redirect(sprintf(
            '/s/participant/%s',
            $id
        ));
    }

    /**
     * @deprecated 2021-02-15
     */
    public function participantsAction(Application $app)
    {
       return $app->redirect('/s/participants/');
    }

    /**
     * @deprecated 2021-03-09
     */
    public function biobank_homeAction(Application $app)
    {
        return $app->redirect('/s/biobank/');
    }
}
