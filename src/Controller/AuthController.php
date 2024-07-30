<?php

namespace App\Controller;

use App\Form\MockLoginType;
use App\Security\User;
use App\Service\AuthService;
use App\Service\EnvironmentService;
use App\Service\SalesforceAuthService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AuthController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/login', name: 'login')]
    public function login(UserService $userService, Request $request, UserProviderInterface $userProvider, EnvironmentService $env, AuthService $authService, SessionInterface $session, ParameterBagInterface $params)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $dashboardUrl = $params->has('dashboard_url') ? $params->get('dashboard_url') : null;
        $displaySalesForceBtn = $params->has('show_salesforce_login_btn') ? $params->get('show_salesforce_login_btn') : true;
        if ($env->isLocal() && $userService->canMockLogin()) {
            $loginForm = $this->createForm(MockLoginType::class);

            $loginForm->handleRequest($request);

            if ($loginForm->isSubmitted() && $loginForm->isValid()) {
                // Set mock user and token for local development
                $email = $loginForm->get('userName')->getData();
                $userService->setMockUser($email);
                /** @var User $user */
                $user = $userProvider->loadUserByUsername($email);
                if (empty($user->getGroups())) {
                    $session->invalidate();
                    return $this->render('error-auth.html.twig', [
                        'email' => $email,
                        'logoutUrl' => $this->generateUrl('logout')
                    ]);
                }
                $authService->setMockAuthToken($user);
                $session->set('isLoginReturn', true);
                return $this->redirect('/');
            }

            return $this->render('login.html.twig', [
                'loginForm' => $loginForm->createView(),
                'dashboardUrl' => $dashboardUrl,
                'displaySalesforceBtn' => $displaySalesForceBtn
            ]);
        }

        return $this->render('login.html.twig', [
            'dashboardUrl' => $dashboardUrl,
            'displaySalesforceBtn' => $displaySalesForceBtn
        ]);
    }

    #[Route(path: '/login/start', name: 'login_start')]
    public function loginStart(AuthService $auth)
    {
        return $this->redirect($auth->getAuthUrl());
    }

    #[Route(path: '/login/callback', name: 'login_callback')]
    public function loginCallback()
    {
        // This never gets executed as it's handled by guard authenticator
        $this->addFlash('error', 'Authentication failed. Please try again.');
        return $this->redirectToRoute('login');
    }

    #[Route(path: '/salesforce', name: 'login_salesforce_request_id')]
    public function loginSalesforceRequestId(
        SalesforceAuthService $auth,
        ContainerBagInterface $params,
        Request $request,
        SessionInterface $session
    ): Response {
        $session->set('ppscRequestId', $request->query->get('requestId'));
        $session->set('ppscLandingPage', $request->query->get('page'));
        $session->set('ppscEnv', $request->query->get('env'));
        if ($params->has('enable_salesforce_login') && $params->get('enable_salesforce_login')) {
            return $this->redirect($auth->getAuthorizationUrl());
        }
        return $this->redirectToRoute('login');
    }

    #[Route(path: '/login/openid/callback', name: 'login_openid_callback')]
    public function loginOpenIdCallback(): Response
    {
        $this->addFlash('error', 'Authentication failed. Please try again.');
        return $this->redirectToRoute('login');
    }
}
