<?php
namespace Pmi\Controller;

use google\appengine\api\users\UserService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pmi\Service\WithdrawalService;
use Pmi\Service\SiteSyncService;

/**
 * NOTE: all /cron routes should be protected by `login: admin` in app.yaml
 */
class CronController extends AbstractController
{
    protected static $name = 'cron';

    protected static $routes = [
        ['pingTest', '/ping-test'],
        ['withdrawal', '/withdrawal'],
        ['sites', '/sites'],
        ['awardeesAndOrganizations', '/awardees-organizations']
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

        $withdrawal = new WithdrawalService($app);
        $withdrawal->sendWithdrawalEmails();

        return (new JsonResponse())->setData(true);
    }
    
    public function pingTestAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $user = UserService::getCurrentUser();
        if ($user) {
            $email = $user->getEmail();
            error_log("Cron ping test requested by $email [" . $request->getClientIp() . "]");
        }
        if ($request->headers->get('X-Appengine-Cron') === 'true') {
            error_log('Cron ping test requested by Appengine-Cron');
        }
        
        return (new JsonResponse())->setData(true);
    }

    public function sitesAction(Application $app, Request $request)
    {
        $action = $request->get('action');
        if (!in_array($action, ['sync', 'preview'])) {
            return (new JsonResponse())->setData(['error' => 'Invalid action']);
        }

        $siteSync = new SiteSyncService(
            $app['pmi.drc.rdrhelper']->getClient(),
            $app['em']
        );
        $syncMayoAccount = !$app->isStable();
        if ($action === 'sync') {
            if (!$app->getConfig('sites_use_rdr')) {
                return (new JsonResponse())->setData(['error' => 'RDR Awardee API disabled']);
            }
            $results = $siteSync->sync($syncMayoAccount);
        } else {
            $results = $siteSync->dryRun($syncMayoAccount);
        }
        return (new JsonResponse())->setData($results);
    }

    public function awardeesAndOrganizationsAction(Application $app)
    {
        $siteSync = new SiteSyncService(
            $app['pmi.drc.rdrhelper']->getClient(),
            $app['em']
        );
        $siteSync->syncAwardees();
        $siteSync->syncOrganizations();
        return (new JsonResponse())->setData(true);
    }
}
