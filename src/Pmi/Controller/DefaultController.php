<?php
namespace Pmi\Controller;

use Pmi\PatientStatus\PatientStatus;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Audit\Log;
use Pmi\Security\User;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['loginReturn', '/login-return'],
        ['keepAlive', '/keepalive', [ 'method' => 'POST' ]],
        ['agreeUsage', '/agree', ['method' => 'POST']],
        ['groups', '/groups'],
        ['hideTZWarning', '/hide-tz-warning', ['method' => 'POST']],
        ['patientStatus', '/participant/{participantId}/patient/status/{patientStatusId}', ['method' => 'GET']]
    ];

    public function loginReturnAction(Application $app)
    {
        //$app['session']->set('isLoginReturn', true);
        $url = $app['session']->get('loginDestUrl', $app->generateUrl('home'));
        return $app->redirect($url);
    }

    /** Dummy action that serves to extend the user's session. */
    public function keepAliveAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('keepAlive', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('pmiLastUsed', time());
        $response = new JsonResponse();
        $response->setData(array());
        return $response;
    }

    public function agreeUsageAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('agreeUsage', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('isUsageAgreed', true);
        return (new JsonResponse())->setData([]);
    }

    public function groupsAction(Application $app)
    {
        $token = $app['security.token_storage']->getToken();
        $user = $token->getUser();
        $groups = $user->getGroups();

        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[] = $group->getName();
        }
        return $app['twig']->render('googlegroups.html.twig', [
            'groupNames' => $groupNames
        ]);
    }

    public function patientStatusAction($participantId, $patientStatusId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $patientStatus = new PatientStatus($app);
        if (!$patientStatus->hasAccess($participant)) {
            $app->abort(403);
        }
        $patientStatusData = $app['em']->getRepository('patient_status')->fetchOneBy([
            'id' => $patientStatusId,
            'participant_id' => $participantId
        ]);
        if (!empty($patientStatusData)) {
            $organization = $patientStatusData['organization'];
            $orgPatientStatusHistoryData = $patientStatus->getOrgPatientStatusHistoryData($participantId, $organization);
            $organization = $patientStatusData['organization'];
        } else {
            $orgPatientStatusHistoryData = [];
            $organization = null;
        }
        return $app['twig']->render('patient-status-history.html.twig', [
            'orgPatientStatusHistoryData' => $orgPatientStatusHistoryData,
            'organization' => $organization
        ]);
    }

    public function hideTZWarningAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('hideTZWarning', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('hideTZWarning', true);
        return (new JsonResponse())->setData([]);
    }
}
