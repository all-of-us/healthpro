<?php
namespace Pmi\Controller;

use Pmi\Service\CacheService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pmi\Service\WithdrawalService;
use Pmi\Service\EvaluationsQueueService;
use Pmi\Service\SiteSyncService;
use Pmi\Service\NotifyMissingMeasurementsAndOrdersService;
use Pmi\Service\PatientStatusService;

/**
 * NOTE: all /cron routes should be protected by `login: admin` in app.yaml
 */
class CronController extends AbstractController
{
    protected static $name = 'cron';

    protected static $routes = [
        ['pingTest', '/ping-test'],
        ['withdrawal', '/withdrawal'],
        ['resendEvaluationsToRdr', '/resend-evaluations-rdr'],
        ['sites', '/sites'],
        ['awardeesAndOrganizations', '/awardees-organizations'],
        ['missingMeasurementsOrders', '/missing-measurements-orders'],
        ['sendPatientStatusToRdr', '/send-patient-status-rdr'],
        ['deleteCacheKeys', '/delete-cache-keys']
    ];
    
    /**
     * Provides a second layer of protection for cron actions beyond the
     * `login: admin` config that should exist in app.yaml for /cron routes.
     */
    private function isAdmin(Request $request)
    {
        return $request->headers->get('X-Appengine-Cron') === 'true';
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

        if ($request->headers->get('X-Appengine-Cron') === 'true') {
            error_log('Cron ping test requested by Appengine-Cron');
        }
        
        return (new JsonResponse())->setData(true);
    }

    public function resendEvaluationsToRdrAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }
        $withdrawal = new EvaluationsQueueService($app);
        $withdrawal->resendEvaluationsToRdr();
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
        $isProd = $app->isProd();
        if ($action === 'sync') {
            if (!$app->getConfig('sites_use_rdr')) {
                return (new JsonResponse())->setData(['error' => 'RDR Awardee API disabled']);
            }
            $results = $siteSync->sync($isProd);
        } else {
            $results = $siteSync->dryRun($isProd);
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

    public function missingMeasurementsOrdersAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $notifyMissing = new NotifyMissingMeasurementsAndOrdersService($app);
        $notifyMissing->sendEmails();

        return (new JsonResponse())->setData(true);
    }

    public function sendPatientStatusToRdrAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }
        $patientStatusService = new PatientStatusService($app);
        $patientStatusService->sendPatientStatusToRdr();
        return (new JsonResponse())->setData(true);
    }

    public function deleteCacheKeysAction(Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $cache = new CacheService();
        $cache->deleteKeys();

        return (new JsonResponse())->setData(true);
    }
}
