<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pmi\Service\EvaluationsQueueService;
use Pmi\Service\PatientStatusService;

class CronController extends AbstractController
{
    protected static $name = 'cron';

    protected static $routes = [
        ['resendEvaluationsToRdr', '/resend-evaluations-rdr'],
        ['sendPatientStatusToRdr', '/send-patient-status-rdr'],
    ];

    private function isAllowed(Application $app, Request $request)
    {
        return $request->headers->get('X-Appengine-Cron') === 'true' || $app->isLocal();
    }

    private function getAccessDeniedResponse()
    {
        return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
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

    public function sendPatientStatusToRdrAction(Application $app, Request $request)
    {
        if (!$this->isAllowed($app, $request)) {
            return $this->getAccessDeniedResponse();
        }

        $patientStatusService = new PatientStatusService($app);
        $patientStatusService->sendPatientStatusToRdr();
        return new JsonResponse(['success' => true]);
    }
}
