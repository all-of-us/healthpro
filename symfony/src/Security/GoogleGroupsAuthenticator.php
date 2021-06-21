<?php

namespace App\Security;

use App\Service\EnvironmentService;
use App\Service\UserService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class GoogleGroupsAuthenticator extends AbstractGuardAuthenticator
{
    private $userService;
    private $params;
    private $env;

    public function __construct(UserService $userService, ContainerBagInterface $params, EnvironmentService $env)
    {
        $this->userService = $userService;
        $this->params = $params;
        $this->env = $env;
    }

    public function supports(Request $request)
    {
        if (($request->getSession()->has('isLogin') && $request->getSession()->has('_security_main')) ||
            (preg_match('/^(\/s)?\/cron\/.*/', $request->getPathInfo()) && ($request->headers->get('X-Appengine-Cron') === 'true' || $this->env->isLocal()))
        ) {
            return false;
        }
        return true;
    }

    public function buildCredentials($googleUser)
    {
        // a user's credentials are effectively their logged-in Google user,
        // supplemented later checking their Google Groups
        return [
            'googleUser' => $googleUser
        ];
    }

    public function getCredentials(Request $request)
    {
        return $this->buildCredentials($this->userService->getGoogleUser());
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if (empty($credentials['googleUser'])) {
            throw new AuthenticationException('No user found');
        }
        return $userProvider->loadUserByUsername($credentials['googleUser']->getEmail());
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $validCredentials = is_array($credentials) && $credentials['googleUser'] &&
            // just a safeguard in case the Google user and our user get out of sync somehow
            strcasecmp($credentials['googleUser']->getEmail(), $user->getEmail()) === 0;

        if (!$this->env->isProd() && $this->params->has('gaBypass') && $this->params->get('gaBypass')) {
            return $validCredentials; // Bypass groups auth
        } else {
            $valid2fa = !$this->params->get('enforce2fa') || $user->hasTwoFactorAuth();
            return $validCredentials && count($user->getGroups()) > 0 && $valid2fa;
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new RedirectResponse('/');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $this->userService->updateLastLogin();
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse('/');
    }

    public function supportsRememberMe()
    {
        // todo
    }
}
