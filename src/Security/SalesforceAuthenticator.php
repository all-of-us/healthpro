<?php

namespace App\Security;

use App\Service\SalesforceAuthService;
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

class SalesforceAuthenticator extends AbstractGuardAuthenticator
{
    private SalesforceAuthService $auth;
    private UrlGeneratorInterface $urlGenerator;
    private UserService $userService;
    private ContainerBagInterface $params;
    private Environment $twig;
    private RequestStack $requestStack;
    private string $authEmail;
    private string $authFailureReason;

    public function __construct(
        SalesforceAuthService $auth,
        UrlGeneratorInterface $urlGenerator,
        ContainerBagInterface $params,
        UserService $userService,
        Environment $twig,
        RequestStack $requestStack
    ) {
        $this->auth = $auth;
        $this->urlGenerator = $urlGenerator;
        $this->params = $params;
        $this->userService = $userService;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request): bool
    {
        if ($this->params->has('enable_salesforce_login') && $this->params->get('enable_salesforce_login') &&
            $request->attributes->get('_route') === 'login_openid_callback') {
            return true;
        }
        return false;
    }

    public function getCredentials(Request $request): ?string
    {
        // Return the authorization code obtained from the OIDC provider's callback
        return $request->query->get('code');
    }

    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        try {
            $user = $this->auth->processAuth($credentials);
            $this->requestStack->getSession()->set('loginType', \App\Entity\User::SALESFORCE);
            $this->requestStack->getSession()->set('program', \App\Entity\User::PROGRAM_HPO);
        } catch (Exception $e) {
            throw new AuthenticationException();
        }

        return $userProvider->loadUserByUsername($user->getEmail());
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        if (!($user instanceof User)) {
            throw new Exception('Invalid user type');
        }
        $this->authEmail = $user->getUsername();
        if (empty($user->getGroups())) {
            $this->authFailureReason = 'groups';
        }
        return count($user->getGroups()) > 0;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        $this->userService->updateLastLogin();
        // Instead of using a service, the token should eventually contain the User entity (not App\Security\User)
        // which will make updating the last login trivial.
        return $this->redirectToRoute('home');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $template = 'error-auth.html.twig';
        if ($this->authFailureReason === 'groups') {
            $template = 'error-auth-salesforce-groups.html.twig';
        }
        $response = new Response($this->twig->render($template, [
            'email' => $this->authEmail,
            'logoutUrl' => ''
        ]));
        $this->requestStack->getSession()->invalidate();
        return $response;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return $this->redirectToRoute('login_openid_start');
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }

    private function redirectToRoute(string $route): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate($route)
        );
    }
}
