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
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Twig\Environment;

class SalesforceAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private SalesforceAuthService $auth;
    private UrlGeneratorInterface $urlGenerator;
    private UserService $userService;
    private ContainerBagInterface $params;
    private Environment $twig;
    private RequestStack $requestStack;
    private string $authEmail = '';
    private string $authFailureReason = '';

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

    public function supports(Request $request): ?bool
    {
        return $this->params->has('enable_salesforce_login')
            && $this->params->get('enable_salesforce_login')
            && $request->attributes->get('_route') === 'login_openid_callback';
    }

    public function authenticate(Request $request): Passport
    {
        if (empty($this->requestStack->getSession()->get('ppscRequestId'))) {
            $this->authFailureReason = 'sessions';
            throw new AuthenticationException('Salesforce session is missing request id.');
        }

        $code = (string) $request->query->get('code');
        if ('' === $code) {
            throw new AuthenticationException('Missing OpenID authorization code.');
        }

        try {
            $user = $this->auth->processAuth($code);
            $this->requestStack->getSession()->set('loginType', \App\Entity\User::SALESFORCE);
            $this->requestStack->getSession()->set('program', \App\Entity\User::PROGRAM_HPO);
        } catch (Exception $e) {
            throw new AuthenticationException('Failed to process Salesforce authentication callback.', 0, $e);
        }

        return new Passport(
            new UserBadge($user->getEmail()),
            new CustomCredentials(function ($credentials, UserInterface $user): bool {
                if (!($user instanceof User)) {
                    throw new AuthenticationException('Invalid user type');
                }
                $this->authEmail = $user->getUserIdentifier();
                if (empty($user->getGroups())) {
                    $this->authFailureReason = 'groups';
                }

                return count($user->getGroups()) > 0;
            }, null)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->userService->updateLastLogin();
        // Instead of using a service, the token should eventually contain the User entity (not App\Security\User)
        // which will make updating the last login trivial.
        return $this->redirectToRoute('home');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $template = 'error-auth-salesforce.html.twig';
        if ($this->authFailureReason === 'sessions') {
            $template = 'error-auth-salesforce-session.html.twig';
        } elseif ($this->authFailureReason === 'groups') {
            $template = 'error-auth-salesforce-groups.html.twig';
        }
        $response = new Response($this->twig->render($template, [
            'email' => $this->authEmail,
            'logoutUrl' => ''
        ]));
        $this->requestStack->getSession()->invalidate();

        return $response;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->redirectToRoute('login_openid_start');
    }

    private function redirectToRoute(string $route): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate($route)
        );
    }
}
