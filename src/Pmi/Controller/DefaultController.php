<?php
namespace Pmi\Controller;

use Silex\Application;
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
        return $app->redirect($app->getGoogleLogoutUrl($timeout ? 'timeout' : 'home'));
    }

    /**
     * This is hack. When authorization fails on the "anonymous" firewall due
     * to IP whitelist, security will redirect the user to a /login route.
     * Rather than write a custom authorization class or something just render
     * the error page here.
     */
    public function loginAction(Application $app, Request $request)
    {
        return $app['twig']->render('error-ip.html.twig');
    }

    public function loginReturnAction(Application $app, Request $request)
    {
        $url = $app['session']->get('loginDestUrl', $app->generateUrl('home'));
        return $app->redirect($url);
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
            $order = $app['em']->getRepository('orders')->fetchOneBy([
                'mayo_id' => $id
            ]);
            if ($order) {
                return $app->redirectToRoute('order', [
                    'participantId' => $order['participant_id'],
                    'orderId' => $order['id']
                ]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $recentOrders = $app['em']->getRepository('orders')->fetchBy(
            [],
            ['created_ts' => 'DESC', 'id' => 'DESC'],
            5
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
        $participant = $app['pmi.drc.participants']->getById($id);
        if (!$participant) {
            $app->abort(404);
        }
        $orders = $app['em']->getRepository('orders')->fetchBy(
            ['participant_id' => $id],
            ['created_ts' => 'DESC', 'id' => 'DESC']
        );
        $evaluations = $app['em']->getRepository('evaluations')->fetchBy(
            ['participant_id' => $id],
            ['updated_ts' => 'DESC', 'id' => 'DESC']
        );
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'evaluations' => $evaluations
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
            $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
                'id' => $evalId,
                'participant_id' => $participantId
            ]);
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
                    if ($evalId = $app['em']->getRepository('evaluations')->insert($dbArray)) {
                        $app->log(Log::EVALUATION_CREATE, $evalId);
                        $app->addFlashNotice('Evaluation saved');
                        return $app->redirectToRoute('participantEval', [
                            'participantId' => $participant->id,
                            'evalId' => $evalId
                        ]);
                    } else {
                        $app->addFlashError('Failed to create new evaluation');
                    }
                } else {
                    if ($app['em']->getRepository('evaluations')->update($evalId, $dbArray)) {
                        $app->log(Log::EVALUATION_EDIT, $evalId);
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
