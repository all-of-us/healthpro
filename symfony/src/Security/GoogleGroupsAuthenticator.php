<?php

namespace App\Security;

use App\Service\AuthService;
use App\Service\EnvironmentService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Exception;

class GoogleGroupsAuthenticator extends AbstractGuardAuthenticator
{
    private $auth;
    private $urlGenerator;
    private $userService;
    private $params;
    private $env;

    public function __construct(AuthService $auth, UrlGeneratorInterface $urlGenerator, ContainerBagInterface $params, EnvironmentService $env)
    {
        $this->auth = $auth;
        $this->urlGenerator = $urlGenerator;
        $this->params = $params;
        $this->env = $env;
    }

    public function supports(Request $request)
    {
        if ($request->attributes->get('_route') === 'login_callback') {
            return true;
        } else {
            return false;
        }
    }

    public function getCredentials(Request $request)
    {
        if (!$request->query->has('state') || !$request->query->has('state')) {
            return null;
        }
        return [
            'state' => $request->query->get('state'),
            'code' => $request->query->get('code')
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        try {
            $user = $this->auth->processAuth($credentials['state'], $credentials['code']);
        } catch (Exception $e) {
            throw new AuthenticationException();
        }

        return $userProvider->loadUserByUsername($user->getEmail());
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($session = $request->getSession()) {
            if ($flashBag = $session->getFlashBag()) {
                $flashBag->add('error', 'Authentication failed. Please try again.');
            }
        }

        return $this->redirectToRoute('login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $this->userService->updateLastLogin();
        return $this->redirectToRoute('symfony_home');
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return $this->redirectToRoute('login');
    }

    public function supportsRememberMe()
    {
        return false;
    }

    private function redirectToRoute(string $route)
    {
        return new RedirectResponse(
            $this->urlGenerator->generate($route)
        );
    }
}
