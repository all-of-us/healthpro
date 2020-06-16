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
use Pmi\WorkQueue\WorkQueue;
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
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}', ['method' => 'GET|POST']],
        ['settings', '/settings', ['method' => 'GET|POST']],
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

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('participantId', TextType::class, [
                'label' => 'Participant ID',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'P000000000'
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $app['pmi.drc.participants']->getById($id);
            if ($participant) {
                return $app->redirectToRoute('participant', ['id' => $id]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $emailForm = $app['form.factory']->createNamedBuilder('email', FormType::class)
            ->add('email', Type\EmailType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'janedoe@example.com'
                ]
            ])
            ->getForm();

        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $searchParameters = $emailForm->getData();
            try {
                $searchResults = $app['pmi.drc.participants']->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $app->redirectToRoute('participant', [
                        'id' => $searchResults[0]->id
                    ]);
                }
                return $app['twig']->render('participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $emailForm->addError(new FormError($e->getMessage()));
            }
        }

        $phoneForm = $app['form.factory']->createNamedBuilder('phone', FormType::class)
            ->add('phone', Type\TelType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => '(999) 999-9999',
                    'class' => 'loginPhone',
                    'pattern' => '^\(?\d{3}\)? ?\d{3}-?\d{4}$',
                    'data-parsley-error-message' => 'This value should be a 10 digit phone number.'
                ]
            ])
            ->getForm();

        $phoneForm->handleRequest($request);

        if ($phoneForm->isSubmitted() && $phoneForm->isValid()) {
            $searchFields = ['loginPhone', 'phone'];
            $searchResults = [];
            foreach ($searchFields as $field) {
                try {
                    $results = $app['pmi.drc.participants']->search([$field => $phoneForm['phone']->getData()]);
                    if (!empty($results)) {
                        foreach ($results as $result) {
                            // Check for duplicates
                            if (isset($searchResults[$result->id])) {
                                continue;
                            }
                            // Set search field type
                            $result->searchField = $field;
                            $searchResults[$result->id] = $result;
                        }
                    }
                } catch (ParticipantSearchExceptionInterface $e) {
                    $phoneForm->addError(new FormError($e->getMessage()));
                }
            }
            return $app['twig']->render('participants-list.html.twig', [
                'participants' => $searchResults,
                'searchType' => 'phone'
            ]);
        }

        $searchForm = $app['form.factory']->createNamedBuilder('search', FormType::class)
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Doe'
                ]
            ])
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new Constraints\Type('string')
                ],
                'required' => false,
                'attr' => [
                    'placeholder' => 'John'
                ]
            ])
            ->add('dob', TextType::class, [
                'label' => 'Date of birth',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => '11/1/1980'
                ]
            ])
            ->getForm();

        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchParameters = $searchForm->getData();
            try {
                $searchResults = $app['pmi.drc.participants']->search($searchParameters);
                return $app['twig']->render('participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $searchForm->addError(new FormError($e->getMessage()));
            }
        }

        return $app['twig']->render('participants.html.twig', [
            'searchForm' => $searchForm->createView(),
            'idForm' => $idForm->createView(),
            'emailForm' => $emailForm->createView(),
            'phoneForm' => $phoneForm->createView()
        ]);
    }

    public function ordersAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('orderId', TextType::class, [
                'label' => 'Order ID',
                'attr' => ['placeholder' => 'Scan barcode or enter order ID'],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();
            
            // New barcodes include a 4-digit sample identifier appended to the 10 digit order id
            // If the string matches this format, remove the sample identifier to get the order id
            if (preg_match('/^\d{14}$/', $id)) {
                $id = substr($id, 0, 10);
            }

            $order = $app['em']->getRepository('orders')->fetchOneBy([
                'order_id' => $id
            ]);
            if ($order) {
                return $app->redirectToRoute('order', [
                    'participantId' => $order['participant_id'],
                    'orderId' => $order['id']
                ]);
            }
            $app->addFlashError('Order ID not found');
        }

        $recentOrders = $app['em']->getRepository('orders')->fetchBySql(
            'site = ? AND created_ts >= ?',
            [$app->getSiteId(), (new \DateTime('-1 day'))->format('Y-m-d H:i:s')],
            ['created_ts' => 'DESC', 'id' => 'DESC']
        );
        foreach ($recentOrders as &$order) {
            $order['participant'] = $app['pmi.drc.participants']->getById($order['participant_id']);
        }
        return $app['twig']->render('orders.html.twig', [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }

    public function participantAction($id, Application $app, Request $request)
    {
        $refresh = $request->query->get('refresh');
        $participant = $app['pmi.drc.participants']->getById($id, $refresh);
        if ($refresh) {
            return $app->redirectToRoute('participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            $app->abort(404);
        }

        $agreeForm = $app['form.factory']->createBuilder(FormType::class)->getForm();
        $agreeForm->handleRequest($request);
        if ($agreeForm->isSubmitted() && $agreeForm->isValid()) {
            $app['session']->set('agreeCrossOrg_'.$id, true);
            $app->log(Log::CROSS_ORG_PARTICIPANT_AGREE, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
            // Check for return url and re-direct
            if ($request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                return $app->redirect($request->query->get('return'));
            }
            return $app->redirectToRoute('participant', [
                'id' => $id
            ]);
        }

        $isCrossOrg = $participant->hpoId !== $app->getSiteOrganization();
        $canViewDetails = !$isCrossOrg && ($participant->status || in_array($participant->statusReason, ['test-participant', 'basics', 'genomics', 'ehr-consent']));
        $hasNoParticipantAccess = $isCrossOrg && empty($app['session']->get('agreeCrossOrg_'.$id));
        if ($hasNoParticipantAccess) {
            $app->log(Log::CROSS_ORG_PARTICIPANT_ATTEMPT, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
        } elseif ($isCrossOrg) {
            $app->log(Log::CROSS_ORG_PARTICIPANT_VIEW, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
        }

        $evaluations = $app['em']->getRepository('evaluations')->getEvaluationsWithHistory($id);
        $orders = $app['em']->getRepository('orders')->getParticipantOrdersWithHistory($id);
        $problems = $app['em']->getRepository('problems')->getParticipantProblemsWithCommentsCount($id);

        if (empty($participant->cacheTime)) {
            $participant->cacheTime = new \DateTime();
        }
        foreach ($orders as $key => $order) {
            // Display most recent processed sample time if exists
            $processedSamplesTs = json_decode($order['processed_samples_ts'], true);
            if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                $processedTs = new \DateTime();
                $processedTs->setTimestamp(max($processedSamplesTs));
                $processedTs->setTimezone(new \DateTimeZone($app->getUserTimezone()));
                $orders[$key]['processed_ts'] = $processedTs;
            }
        }
        // Determine cancel route
        $cancelRoute = 'participants';
        if ($request->query->has('return')) {
            if (strpos($request->query->get('return'), '/order/') !== false) {
                $cancelRoute = 'orders';
            }
        }

        $patientStatus = new PatientStatus($app);
        // Check if patient status is allowed for this participant
        if ($patientStatus->hasAccess($participant)) {
            // Patient Status
            $patientStatus = new PatientStatus($app);
            $orgPatientStatusData = $patientStatus->getOrgPatientStatusData($id);
            // Determine if comment field is required
            $isCommentRequired = !empty($orgPatientStatusData) ? true : false;
            // Get patient status form
            $patientStatusForm = $patientStatus->getForm($isCommentRequired);
            $patientStatusForm->handleRequest($request);
            if ($patientStatusForm->isSubmitted()) {
                $patientStatusData = $app['em']->getRepository('patient_status')->fetchOneBy([
                    'participant_id' => $id,
                    'organization' => $app->getSiteOrganizationId()
                ]);
                if (!empty($patientStatusData) && empty($patientStatusForm['comments']->getData())) {
                    $patientStatusForm['comments']->addError(new FormError('Please enter comment'));
                }
                if ($patientStatusForm->isValid()) {
                    $patientStatusId = !empty($patientStatusData) ? $patientStatusData['id'] : null;
                    $patientStatus->loadData($id, $patientStatusId, $patientStatusForm->getData());
                    if ($patientStatus->sendToRdr() && $patientStatus->saveData()) {
                        $app->addFlashSuccess('Patient status saved');
                        // Load newly entered data
                        $orgPatientStatusData = $patientStatus->getOrgPatientStatusData($id);
                        // Get new form
                        $patientStatusForm = $patientStatus->getForm(true);
                    } else {
                        $app->addFlashError("Failed to create patient status. Please try again.");
                    }
                } else {
                    $patientStatusForm->addError(new FormError('Please correct the errors below'));
                }
            }
            $orgPatientStatusHistoryData = $patientStatus->getOrgPatientStatusHistoryData($id, $app->getSiteOrganizationId());
            $awardeePatientStatusData = $patientStatus->getAwardeePatientStatusData($id);
            $patientStatusForm = $patientStatusForm->createView();
            $canViewPatientStatus = $patientStatus->hasAccess($participant);
        } else {
            $patientStatusForm = null;
            $orgPatientStatusData = null;
            $orgPatientStatusHistoryData = null;
            $awardeePatientStatusData = null;
            $canViewPatientStatus = false;
        }
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'evaluations' => $evaluations,
            'problems' => $problems,
            'hasNoParticipantAccess' => $hasNoParticipantAccess,
            'agreeForm' => $agreeForm->createView(),
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled(),
            'canViewDetails' => $canViewDetails,
            'samples' => WorkQueue::$samples,
            'surveys' => WorkQueue::$surveys,
            'samplesAlias' => WorkQueue::$samplesAlias,
            'cancelRoute' => $cancelRoute,
            'patientStatusForm' => $patientStatusForm,
            'orgPatientStatusData' => $orgPatientStatusData,
            'orgPatientStatusHistoryData' => $orgPatientStatusHistoryData,
            'awardeePatientStatusData' => $awardeePatientStatusData,
            'isDVType' => $app->isDVType(),
            'canViewPatientStatus' => $canViewPatientStatus,
            'displayPatientStatusBlock' => !$app->isDVType()
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

    public function settingsAction(Application $app, Request $request)
    {
        $settingsData = ['timezone' => $app->getUserTimezone(false)];
        $settingsForm = $app['form.factory']->createBuilder(FormType::class, $settingsData)
            ->add('timezone', Type\ChoiceType::class, [
                'label' => 'Time zone',
                'choices' => array_flip($app::$timezoneOptions),
                'placeholder' => '-- Select your time zone --',
                'constraints' => new Constraints\NotBlank()
            ])
            ->getForm();

        $settingsForm->handleRequest($request);
        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $app['em']->getRepository('users')->update($app->getUserId(), [
                'timezone' => $settingsForm['timezone']->getData()
            ]);
            $app->addFlashSuccess('Your settings have been updated');
            if ($request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                return $app->redirect($request->query->get('return'));
            } else {
                return $app->redirectToRoute('home');
            }
        }

        return $app['twig']->render('settings.html.twig', [
            'settingsForm' => $settingsForm->createView()
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
