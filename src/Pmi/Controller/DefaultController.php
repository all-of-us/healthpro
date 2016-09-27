<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Pmi\Evaluation\Evaluation;
use Pmi\Mayolink\Order as MayoLinkOrder;
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;
use google\appengine\api\users\UserService;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/'],
        ['logout', '/logout'],
        ['login', '/login'],
        ['loginReturn', '/login-return'],
        ['timeout', '/timeout'],
        ['keepAlive', '/keepalive', [ 'method' => 'POST' ]],
        ['clientTimeout', '/client-timeout', [ 'method' => 'GET' ]],
        ['agreeUsage', '/agree', ['method' => 'POST']],
        ['groups', '/groups'],
        ['switchSite', '/site/{id}/switch'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}'],
        ['orderCreate', '/participant/{participantId}/order/create', [
            'method' => 'GET|POST'
        ]],
        ['participantEval', '/participant/{participantId}/eval/{evalId}', [
            'method' => 'GET|POST',
            'defaults' => ['evalId' => null]
        ]]
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('index.html.twig');
    }
    
    public function logoutAction(Application $app, Request $request)
    {
        $timeout = $request->get('timeout');
        $app->log(Log::LOGOUT);
        $app->logout();
        return $app->redirect($app->getGoogleLogoutUrl($timeout ? $app->generateUrl('timeout') : null));
    }
    
    protected function getAuthLoginClient($app, $state = null)
    {
        if ($state) {
            $client = new \Google_Client([
                'state' => $state
            ]);
        } else {
            $client = new \Google_Client();
        }
        $client->setClientId($app->getConfig('auth_client_id'));
        $client->setClientSecret($app->getConfig('auth_client_secret'));

        if ($app->getConfig('login_url')) {
            $path = $app->getConfig('login_url');
            $path = preg_replace('/\/$/', '', $path);
            $callbackUrl = $path . $app['url_generator']->generate('loginReturn');
        } else {
            $callbackUrl = $app['url_generator']->generate('loginReturn', [], \Symfony\Component\Routing\Generator\UrlGenerator::ABSOLUTE_URL);
        }
        $client->setRedirectUri($callbackUrl);
        $client->setScopes(['email', 'profile']);
        return $client;
    }

    public function loginAction(Application $app, Request $request)
    {
        $ips = $app->getIpWhitelist();
        if (is_array($ips) && count($ips) > 0 && !IpUtils::checkIp($request->getClientIp(), $ips)) {
            return $app['twig']->render('error-ip.html.twig');
        } else {
            $authState = sha1(openssl_random_pseudo_bytes(1024));
            $app['session']->set('auth_state', $authState);
            $client = $this->getAuthLoginClient($app, $authState);
            return $app->redirect($client->createAuthUrl());
        }
    }

    public function loginReturnAction(Application $app, Request $request)
    {
        if (!$request->query->get('state') || $request->query->get('state') != $app['session']->get('auth_state')) {
            return $app->abort(403);
        }

        $client = $this->getAuthLoginClient($app);
        $token = $client->fetchAccessTokenWithAuthCode($request->query->get('code'));
        $client->setAccessToken($token);
        $idToken = $client->verifyIdToken();
        $userEmail = $idToken['email'];
        $userId = $idToken['sub'];
        // TODO: connect to symfony auth
    }
    
    public function timeoutAction(Application $app, Request $request)
    {
        return $app['twig']->render('timeout.html.twig');
    }
    
    /** Dummy action that serves to extend the user's session. */
    public function keepAliveAction(Application $app, Request $request) {
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
        $request->getSession()->set('isUsageAgreed', true);
        return (new JsonResponse())->setData([]);
    }
    
    public function groupsAction(Application $app, Request $request)
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
    
    public function switchSiteAction($id, Application $app, Request $request)
    {
        $user = $app['security.token_storage']->getToken()->getUser();
        if ($user->belongsToSite($id)) {
            $app['session']->set('site', $user->getSite($id));
            return $app->redirectToRoute('home');
        } else {
            return $app->abort(403);
        }
    }

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('participantId', TextType::class, ['label' => 'Participant ID'])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $app['pmi.drc.participants']->getById($id);
            if ($participant) {
                return $app->redirectToRoute('participant', ['id' => $id]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $searchForm = $app['form.factory']->createNamedBuilder('search', FormType::class)
            ->add('lastName', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'Doe'
                ]
            ])
            ->add('firstName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'John'
                ]
            ])
            ->add('dob', TextType::class, [
                'label' => 'Date of birth',
                'required' => true,
                'attr' => [
                    'placeholder' => '11/1/1980'
                ]
            ])
            ->getForm();

        $searchForm->handleRequest($request);

        if ($searchForm->isValid()) {
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
            'idForm' => $idForm->createView()
        ]);
    }

    public function ordersAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('mayoId', TextType::class, ['label' => 'MayoLINK order ID', 'attr' => ['placeholder' => 'Scan barcode']])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('mayoId')->getData();
            $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE mayo_id=?', [$id]);
            if ($order) {
                return $app->redirectToRoute('order', [
                    'participantId' => $order['participant_id'],
                    'orderId' => $order['id']
                ]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $recentOrders = $app['db']->fetchAll('SELECT * FROM orders ORDER BY created_ts DESC, id DESC LIMIT 5');
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
        $participant = $app['pmi.drc.participants']->getById($id);
        if (!$participant) {
            $app->abort(404);
        }
        $orders = $app['db']->fetchAll('SELECT * FROM orders WHERE participant_id = ? ORDER BY created_ts DESC, id DESC', [$id]);
        $evaluations = $app['db']->fetchAll('SELECT * FROM evaluations WHERE participant_id = ? ORDER BY updated_ts DESC, id DESC', [$id]);
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'evaluations' => $evaluations
        ]);
    }

    public function orderCreateAction($participantId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->consentComplete) {
            $app->abort(403);
        }
        $confirmForm = $app['form.factory']->createBuilder(FormType::class)
            ->add('confirm', HiddenType::class)
            ->getForm();
        $confirmForm->handleRequest($request);
        if ($confirmForm->isValid()) {
            if ($app->getConfig('ml_mock_order')) {
                $mlOrderId = $app->getConfig('ml_mock_order');
            } else {
                $order = new MayoLinkOrder();
                $options = [
                    // TODO: figure out test code, specimen, and temperature parameters
                    'test_code' => 'ACE',
                    'specimen' => 'Serum',
                    'temperature' => 'Ambient',
                    'first_name' => '*',
                    'last_name' => $participant->id,
                    'gender' => $participant->gender,
                    'birth_date' => $participant->dob,
                    'physician_name' => 'None',
                    'physician_phone' => 'None',
                    // TODO: not sure how ML is handling time zone. setting to yesterday for now
                    'collected_at' => new \DateTime('-1 day')
                ];
                $mlOrderId = $order->loginAndCreateOrder(
                    $app->getConfig('ml_username'),
                    $app->getConfig('ml_password'),
                    $options
                );
            }
            if ($mlOrderId) {
                $success = $app['db']->insert('orders', [
                    'participant_id' => $participant->id,
                    'created_ts' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'mayo_id' => $mlOrderId
                ]);
                if ($success && ($orderId = $app['db']->lastInsertId())) {
                    $app->log(Log::ORDER_CREATE, $orderId);
                    return $app->redirectToRoute('order', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId
                    ]);
                }
            }
            $app->addFlashError('Failed to create order.');
        }

        return $app['twig']->render('order-create.html.twig', [
            'participant' => $participant,
            'confirmForm' => $confirmForm->createView()
        ]);
    }

    public function participantEvalAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->consentComplete) {
            $app->abort(403);
        }
        $evaluationService = new Evaluation();
        if ($evalId) {
            $evaluation = $app['db']->fetchAssoc('SELECT * FROM evaluations WHERE id = ? AND participant_id = ?', [$evalId, $participantId]);
            if (!$evaluation) {
                $app->abort(404);
            }
            $evaluationService->loadFromArray($evaluation);
        } else {
            $evaluation = null;
        }
        $evaluationForm = $evaluationService->getForm($app['form.factory']);
        $evaluationForm->handleRequest($request);
        if ($evaluationForm->isSubmitted()) {
            if ($evaluationForm->isValid()) {
                $evaluationService->setData($evaluationForm->getData());
                $dbArray = $evaluationService->toArray();
                $dbArray['updated_ts'] = (new \DateTime())->format('Y-m-d H:i:s');
                if (!$evaluation) {
                    $dbArray['participant_id'] = $participant->id;
                    $dbArray['created_ts'] = $dbArray['updated_ts'];
                    if ($app['db']->insert('evaluations', $dbArray) && ($evalId = $app['db']->lastInsertId())) {
                        $app->log(Log::EVALUATION_CREATE, $evalId);
                        $app['pmi.drc.participants']->createEvaluation($participant->id, [
                            'evaluation_id' => $evalId,
                            'evaluation_version' => $dbArray['version'],
                            'evaluation_data' => $dbArray['data']
                        ]);
                        $app->addFlashNotice('Evaluation saved');
                        return $app->redirectToRoute('participantEval', [
                            'participantId' => $participant->id,
                            'evalId' => $evalId
                        ]);
                    } else {
                        $app->addFlashError('Failed to create new evaluation');
                    }
                } else {
                    if ($app['db']->update('evaluations', $dbArray, ['id' => $evalId])) {
                        $app->log(Log::EVALUATION_EDIT, $evalId);
                        $result = $app['pmi.drc.participants']->updateEvaluation($participant->id, $evalId, [
                            'evaluation_version' => $dbArray['version'],
                            'evaluation_data' => $dbArray['data']
                        ]);
                        $app->addFlashNotice('Evaluation saved');
                        return $app->redirectToRoute('participantEval', [
                            'participantId' => $participant->id,
                            'evalId' => $evalId
                        ]);
                    } else {
                        $app->addFlashError('Failed to update evaluation');
                    }
                }
            } else {
                if (count($evaluationForm->getErrors()) == 0) {
                    $evaluationForm->addError(new FormError('Please correct the errors below'));
                }
            }
        }

        return $app['twig']->render('evaluation.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluationForm' => $evaluationForm->createView(),
            'schema' => $evaluationService->getSchema()
        ]);
    }
}
