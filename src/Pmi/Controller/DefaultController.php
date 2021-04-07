<?php
namespace Pmi\Controller;

use Pmi\Evaluation\Evaluation;
use Pmi\PatientStatus\PatientStatus;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Audit\Log;
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;
use Pmi\Order\Order;
use Pmi\Security\User;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/'],
        ['dashSplash', '/splash'],
        ['logout', '/logout'],
        ['login', '/login'],
        ['loginReturn', '/login-return'],
        ['timeout', '/timeout'],
        ['keepAlive', '/keepalive', [ 'method' => 'POST' ]],
        ['clientTimeout', '/client-timeout', [ 'method' => 'GET' ]],
        ['agreeUsage', '/agree', ['method' => 'POST']],
        ['groups', '/groups'],
        ['selectSite', '/site/select', ['method' => 'GET|POST']],
        ['hideTZWarning', '/hide-tz-warning', ['method' => 'POST']],
        ['patientStatus', '/participant/{participantId}/patient/status/{patientStatusId}', ['method' => 'GET']],
        ['mockLogin', '/mock-login', ['method' => 'GET|POST']]
    ];

    public function homeAction(Application $app)
    {
        $checkTimeZone = $app->hasRole('ROLE_USER') || $app->hasRole('ROLE_ADMIN') || $app->hasRole('ROLE_AWARDEE') || $app->hasRole('ROLE_DV_ADMIN');
        if ($checkTimeZone && !$app->getUserTimezone(false)) {
            $app->addFlashNotice('Please select your current time zone');
            return $app->redirectToRoute('settings');
        }
        if ($app->hasRole('ROLE_USER') || ($app->hasRole('ROLE_AWARDEE') && $app->hasRole('ROLE_DV_ADMIN'))) {
            return $app['twig']->render('index.html.twig');
        } elseif ($app->hasRole('ROLE_AWARDEE')) {
            return $app->redirectToRoute('workqueue_index');
        } elseif ($app->hasRole('ROLE_DV_ADMIN')) {
            return $app->redirectToRoute('problem_reports');
        } elseif ($app->hasRole('ROLE_ADMIN')) {
            return $app->redirectToRoute('admin_home');
        } elseif ($app->hasRole('ROLE_DASHBOARD')) {
            return $app->redirectToRoute('dashboard_home');
        } elseif ($app->hasRole('ROLE_BIOBANK') || $app->hasRole('ROLE_SCRIPPS')) {
            return $app->redirectToRoute('biobank_home');
        } else {
            return $app->abort(403);
        }
    }

    public function dashSplashAction(Application $app)
    {
        return $app['twig']->render('dash-splash.html.twig');
    }

    public function logoutAction(Application $app, Request $request)
    {
        $timeout = $request->get('timeout');
        $app->log(Log::LOGOUT);
        $app->logout();
        return $app->redirect($app->getGoogleLogoutUrl($timeout ? 'timeout' : 'home'));
    }

    public function loginReturnAction(Application $app)
    {
        $app['session']->set('isLoginReturn', true);
        $url = $app['session']->get('loginDestUrl', $app->generateUrl('home'));
        return $app->redirect($url);
    }

    public function timeoutAction(Application $app)
    {
        return $app['twig']->render('timeout.html.twig');
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

    /**
     * Handles a clientside session timeout, which might not be a true session
     * timeout if the user is working in multiple tabs.
     */
    public function clientTimeoutAction(Application $app, Request $request) {
        // if we got to this point, then the beforeCallback() has
        // already checked the user's session is not expired - simply reload the page
        if ($request->headers->get('referer')) {
            return $app->redirect($request->headers->get('referer'));
        } else {
            return $app->redirect($app->generateUrl('home'));
        }
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

    public function selectSiteAction(Application $app, Request $request)
    {
        if ($request->request->has('site')) {
            if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('siteSelect', $request->request->get('csrf_token')))) {
                return $app->abort(403);
            }
            $siteId = $request->request->get('site');
            if (strpos($siteId, User::AWARDEE_PREFIX) !== 0 && !$app->isValidSite($siteId)) {
                $app->addFlashError("Sorry, there is a problem with your site's configuration. Please contact your site administrator.");
                return $app['twig']->render('site-select.html.twig', ['siteEmail' => $siteId]);
            }
            if ($app->switchSite($siteId)) {
                return $app->redirectToRoute('home');
            } else {
                return $app->abort(403);
            }
        }
        return $app['twig']->render('site-select.html.twig');
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

    public function mockLoginAction(Application $app, Request $request)
    {
        if (!$app->canMockLogin()){
            return $app->abort(403);
        }
        $loginForm = $app['form.factory']->createNamedBuilder('login', FormType::class)
            ->add('userName', TextType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'value' => 'test@example.com'
                ]
            ])
            ->getForm();

        $loginForm->handleRequest($request);

        if ($loginForm->isSubmitted() && $loginForm->isValid()) {
            // Set mock user for local development
            $app->setMockUser($loginForm->get('userName')->getData());
            return $app->redirect('/');
        }

        return $app['twig']->render('mock-login.html.twig', [
            'loginForm' => $loginForm->createView()
        ]);
    }
}
