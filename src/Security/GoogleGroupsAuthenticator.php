<?php

namespace App\Security;

use App\Service\AuthService;
use App\Service\EnvironmentService;
use App\Service\UserService;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Twig\Environment;

class GoogleGroupsAuthenticator extends AbstractGuardAuthenticator
{
    private $auth;
    private $urlGenerator;
    private $userService;
    private $params;
    private $env;
    private $twig;
    private $requestStack;
    private $authEmail;
    private $authFailureReason;

    public function __construct(AuthService $auth, UrlGeneratorInterface $urlGenerator, ContainerBagInterface $params, EnvironmentService $env, UserService $userService, Environment $twig, RequestStack $requestStack)
    {
        $this->auth = $auth;
        $this->urlGenerator = $urlGenerator;
        $this->params = $params;
        $this->env = $env;
        $this->userService = $userService;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request): bool
    {
        if ($request->attributes->get('_route') === 'login_callback' && !$this->userService->canMockLogin()) {
            return true;
        }
        return false;
    }

    /**
     * @return mixed
     */
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

    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        try {
            $user = $this->auth->processAuth($credentials['state'], $credentials['code']);
            $this->requestStack->getSession()->set('loginType', null);
        } catch (Exception $e) {
            throw new AuthenticationException();
        }

        return $userProvider->loadUserByUsername($user->getEmail());
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        if (!$this->env->isProd() && $this->params->has('gaBypass') && $this->params->get('gaBypass')) {
            return true; // Bypass groups auth
        }
        if (!($user instanceof User)) {
            throw new Exception('Invalid user type');
        }

        $valid2fa = !($this->params->has('enforce2fa') && $this->params->get('enforce2fa')) || $user->hasTwoFactorAuth();
        $this->authEmail = $user->getUsername();
        if (!$valid2fa) {
            $this->authFailureReason = '2fa';
        } elseif (empty($user->getGroups())) {
            $this->authFailureReason = 'groups';
        }
        return count($user->getGroups()) > 0 && $valid2fa;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $template = 'error-auth.html.twig';
        if ($this->authFailureReason === '2fa') {
            $template = 'error-auth-2fa.html.twig';
        } elseif ($this->authFailureReason === 'groups') {
            $template = 'error-auth-groups.html.twig';
        }
        $response = new Response($this->twig->render($template, [
            'email' => $this->authEmail,
            'logoutUrl' => $this->auth->getGoogleLogoutUrl()
        ]));
        $this->requestStack->getSession()->invalidate();
        return $response;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        $this->userService->updateLastLogin();
        // Instead of using a service, the token should eventually contain the User entity (not App\Security\User)
        // which will make updating the last login trivial.
        return $this->redirectToRoute('home');
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return $this->redirectToRoute('login');
    }

    public function supportsRememberMe(): bool
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
