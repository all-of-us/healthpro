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
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;

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
        ['switchSite', '/site/{id}/switch', ['method' => 'GET|POST']],
        ['selectSite', '/site/select'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}'],
    ];

    public function homeAction(Application $app, Request $request)
    {
        if ($app->hasRole('ROLE_USER')) {
            return $app['twig']->render('index.html.twig');
        } elseif ($app->hasRole('ROLE_DASHBOARD')) {
            return $app->redirectToRoute('dashboard_home');
        } else {
            return $app->abort(403);
        }
    }
    
    public function dashSplashAction(Application $app, Request $request)
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
        $app['session']->set('isLoginReturn', true);
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
        if ($app->switchSite($id)) {
            return $app->redirect($request->get('destUrl', $app->generateUrl('home')));
        } else {
            return $app->abort(403);
        }
    }
    
    public function selectSiteAction(Application $app, Request $request)
    {
        $destUrl = $request->get('destUrl', $app->generateUrl('home'));
        return $app['twig']->render('site-select.html.twig', ['destUrl' => $destUrl]);
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
            ->add('mayoId', TextType::class, ['label' => 'Order ID', 'attr' => ['placeholder' => 'Scan barcode']])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('mayoId')->getData();
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
}
