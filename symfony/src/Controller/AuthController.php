<?php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s")
 */
class AuthController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login()
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('symfony_home');
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
            exit;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Authentication failed. Please try again.');

            return $this->redirectToRoute('login');
        }
    }
}
