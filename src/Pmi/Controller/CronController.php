<?php
namespace Pmi\Controller;

use google\appengine\api\users\UserService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * NOTE: all /cron routes should be protected by `login: admin` in app.yaml
 */
class CronController extends AbstractController
{
    protected static $name = 'cron';

    protected static $routes = [
        ['dashboard', '/'],
        ['pingTest', '/ping-test'],
        ['withdrawal', '/withdrawal']
    ];
    
    /**
     * Provides a second layer of protection for cron actions beyond the
     * `login: admin` config that should exist in app.yaml for /cron routes.
     */
    private function isAdmin(Request $request)
    {
        return UserService::isCurrentUserAdmin() ||
            $request->headers->get('X-Appengine-Cron') === 'true';
    }

    public function withdrawalAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $rows = $app['db']->fetchAll('SELECT distinct organization FROM sites where organization is not null');
        foreach ($rows as $row) {
            $organization = $row['organization'];
            $searchParams = [
                'withdrawalStatus' => 'NO_USE',
                'hpoId' => $organization,
                '_sort:desc' => 'withdrawalTime'
            ];
            // TODO: filter by withdrawal time
            $summaries = $app['pmi.drc.participants']->listParticipantSummaries($searchParams);
            $message = "The following participants have withdrawn ({$organization}):\n\n";
            foreach ($summaries as $summary) {
                // TODO: check log
                $participantId = $summary->resource->participantId;
                // TODO: insert into log
                $message .= "{$participantId}\n";
            }
            // TODO: Send email
        }
        
        return (new JsonResponse())->setData(true);
    }

    public function dashboardAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }
        
        $request->getSession()->getFlashBag()->add('error', 'Not yet implemented!');
        return $app->redirectToRoute('home');
    }
    
    public function pingTestAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }
        
        $email = UserService::getCurrentUser()->getEmail();
        error_log("Cron ping test requested by $email [" . $request->getClientIp() . "]");
        
        return (new JsonResponse())->setData(true);
    }
}
