<?php

namespace App\Controller;

use App\Form\MockLoginType;
use App\Service\AuthService;
use App\Service\EnvironmentService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @Route("/s")
 */
class AuthController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(UserService $userService, Request $request, UserProviderInterface $userProvider, EnvironmentService $env, AuthService $authService, SessionInterface $session)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('symfony_home');
        }

        if ($env->isLocal() && $userService->canMockLogin()) {
            $loginForm = $this->createForm(MockLoginType::class);

            $loginForm->handleRequest($request);

            if ($loginForm->isSubmitted() && $loginForm->isValid()) {
                // Set mock user and token for local development
                $userService->setMockUser($loginForm->get('userName')->getData());
                $user = $userProvider->loadUserByUsername($loginForm->get('userName')->getData());
                $authService->setMockAuthToken($user);
                $session->set('isLoginReturn', true);
                return $this->redirect('/s');
            }

            return $this->render('mock-login.html.twig', [
                'loginForm' => $loginForm->createView()
            ]);
        }

        return $this->render('login.html.twig');
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
    public function loginCallback(AuthService $auth, Request $request, SessionInterface $session)
    {
        $state = $request->query->get('state');
        $code = $request->query->get('code');
        try {
            $user = $auth->processAuth($state, $code);
            $session->set('googleUser', $user);
            echo 'Logged in as ' . $user->getEmail();
            $session->set('isLoginReturn', true);
            exit;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Authentication failed. Please try again.');

            return $this->redirectToRoute('login');
        }
    }
}
