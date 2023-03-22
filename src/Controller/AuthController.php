<?php

namespace App\Controller;

use App\Form\MockLoginType;
use App\Security\User;
use App\Service\AuthService;
use App\Service\EnvironmentService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AuthController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(UserService $userService, Request $request, UserProviderInterface $userProvider, EnvironmentService $env, AuthService $authService, SessionInterface $session, ParameterBagInterface $params)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $dashboardUrl = $params->has('dashboard_url') ? $params->get('dashboard_url') : null; // @phpstan-ignore-line
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
                'dashboardUrl' => $dashboardUrl
            ]);
        }

        return $this->render('login.html.twig', [
            'dashboardUrl' => $dashboardUrl
        ]);
    }

    /**
     * @Route("/login/start", name="login_start")
     */
    public function loginStart(AuthService $auth)
    {
        return $this->redirect($auth->getAuthUrl());
    }

    /**
     * @Route("/login/callback", name="login_callback")
     */
    public function loginCallback()
    {
        // This never gets executed as it's handled by guard authenticator
        $this->addFlash('error', 'Authentication failed. Please try again.');
        return $this->redirectToRoute('login');
    }
}
