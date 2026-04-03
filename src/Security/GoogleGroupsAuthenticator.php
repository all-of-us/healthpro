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
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Twig\Environment;

class GoogleGroupsAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
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

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'login_callback' && !$this->userService->canMockLogin();
    }

    public function authenticate(Request $request): Passport
    {
        if (!$request->query->has('state') || !$request->query->has('code')) {
            throw new AuthenticationException('Missing authentication callback parameters.');
        }

        try {
            $user = $this->auth->processAuth(
                (string) $request->query->get('state'),
                (string) $request->query->get('code')
            );
            $this->requestStack->getSession()->set('loginType', null);
        } catch (Exception $e) {
            throw new AuthenticationException('Failed to process Google authentication callback.', 0, $e);
        }

        return new Passport(
            new UserBadge($user->getEmail()),
            new CustomCredentials(function ($credentials, UserInterface $user): bool {
                if (!$this->env->isProd() && $this->params->has('gaBypass') && $this->params->get('gaBypass')) {
                    return true; // Bypass groups auth
                }
                if (!($user instanceof User)) {
                    throw new AuthenticationException('Invalid user type');
                }

                $valid2fa = !($this->params->has('enforce2fa') && $this->params->get('enforce2fa')) || $user->hasTwoFactorAuth();
                $this->authEmail = $user->getUserIdentifier();
                if (!$valid2fa) {
                    $this->authFailureReason = '2fa';
                } elseif (empty($user->getGroups())) {
                    $this->authFailureReason = 'groups';
                }

                return count($user->getGroups()) > 0 && $valid2fa;
            }, null)
        );
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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->userService->updateLastLogin();
        // Instead of using a service, the token should eventually contain the User entity (not App\Security\User)
        // which will make updating the last login trivial.
        return $this->redirectToRoute('home');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->redirectToRoute('login');
    }

    private function redirectToRoute(string $route): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate($route)
        );
    }
}
