<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pmi\Service\WithdrawalService;
use Pmi\Service\EvaluationsQueueService;
use Pmi\Service\SiteSyncService;
use Pmi\Service\NotifyMissingMeasurementsAndOrdersService;
use Pmi\Service\PatientStatusService;
use Pmi\Datastore\DatastoreSessionHandler;

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
        ['deleteCacheKeys', '/delete-cache-keys'],
        ['deleteSessionKeys', '/delete-session-keys']
    ];

    private function isAllowed(Application $app, Request $request)
    {
        return $request->headers->get('X-Appengine-Cron') === 'true' || $app->isLocal();
    }

    private function getAccessDeniedResponse()
    {
        return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }

    public function withdrawalAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $withdrawal = new WithdrawalService($app);
        $withdrawal->sendWithdrawalEmails();

        return new JsonResponse(['success' => true]);
    }


    public function pingTestAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }
        
        return new JsonResponse(['success' => true]);
    }

    public function resendEvaluationsToRdrAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $withdrawal = new EvaluationsQueueService($app);
        $withdrawal->resendEvaluationsToRdr();
        return new JsonResponse(['success' => true]);
    }

    public function sitesAction(Application $app)
    {
        if (!$app->getConfig('sites_use_rdr')) {
            return (new JsonResponse())->setData(['error' => 'RDR Awardee API disabled']);
        }
        $siteSync = new SiteSyncService($app);
        $isProd = $app->isProd();
        $siteSync->sync($isProd);
        return (new JsonResponse())->setData(true);
    }

    public function awardeesAndOrganizationsAction(Application $app, Request $request)
    {
        if (!$app->getConfig('sites_use_rdr')) {
            return (new JsonResponse())->setData(['error' => 'RDR Awardee API disabled']);
        }
        $siteSync = new SiteSyncService($app);
        $siteSync->syncAwardees();
        $siteSync->syncOrganizations();
        return new JsonResponse(['success' => true]);
    }

    public function missingMeasurementsOrdersAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $notifyMissing = new NotifyMissingMeasurementsAndOrdersService($app);
        $notifyMissing->sendEmails();

        return new JsonResponse(['success' => true]);
    }

    public function sendPatientStatusToRdrAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $patientStatusService = new PatientStatusService($app);
        $patientStatusService->sendPatientStatusToRdr();
        return new JsonResponse(['success' => true]);
    }

    public function deleteCacheKeysAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $app['cache']->prune();

        return new JsonResponse(['success' => true]);
    }

    public function deleteSessionKeysAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $dataStoreSessionHandler =  new DatastoreSessionHandler($app->getConfig('ds_clean_up_limit'));
        $dataStoreSessionHandler->gc($app['sessionTimeout']);

        return new JsonResponse(['success' => true]);
    }
}
