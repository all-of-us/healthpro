<?php

namespace App\Service;

use Pmi\Drc\GoogleUser;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Exception;
use Google_Client as GoogleClient;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuthService
{
    private $params;
    private $session;
    private $urlGenerator;
    private $callbackUrl;
    private $tokenStorage;
    private $env;

    public function __construct(
        ContainerBagInterface $params,
        SessionInterface $session,
        UrlGeneratorInterface $urlGenerator,
        TokenStorageInterface $tokenStorage,
        EnvironmentService $env
    ) {
        $this->params = $params;
        $this->session = $session;
        $this->urlGenerator = $urlGenerator;
        $this->callbackUrl = $this->generateUrl('login_callback');
        $this->tokenStorage = $tokenStorage;
        $this->env = $env;
    }

    private function getGoogleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($this->params->get('auth_client_id'));
        $client->setClientSecret($this->params->get('auth_client_secret'));
        $client->setRedirectUri($this->callbackUrl);
        $client->setScopes(['email', 'profile']);

        return $client;
    }

    private function setSessionState(string $state): void
    {
        $this->session->set('auth_state', $state);
    }

    private function getSessionState(): ?string
    {
        return $this->session->get('auth_state');
    }

    public function getAuthUrl(): string
    {
        $state = sha1(openssl_random_pseudo_bytes(1024));
        $this->setSessionState($state);
        $client = $this->getGoogleClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function processAuth(string $state, string $code): GoogleUser
    {
        $sessionState = $this->getSessionState();
        if (empty($state) || empty($sessionState) || $state !== $sessionState) {
            throw new Exception('Unexpected state');
        }
        $client = $this->getGoogleClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        $client->setAccessToken($token);
        $idToken = $client->verifyIdToken();
        if (empty($idToken) || empty($idToken['sub']) || empty($idToken['email'])) {
            throw new Exception('Could not verify token');
        }
        $user = new GoogleUser($idToken['sub'], $idToken['email']);
        $this->session->set('googleUser', $user);

        return $user;
    }

    public function setMockAuthToken($user)
    {
        $token = new PreAuthenticatedToken($user, null, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
    }

    public function getGoogleLogoutUrl($route = 'symfony_home')
    {
        $dest = $this->generateUrl($route);
        
        if ($this->env->isLocal() && $this->params->has('local_mock_auth') && $this->params->get('local_mock_auth')) {
            return $this->env->values['isUnitTest'] ? null : $dest;
        }
        // http://stackoverflow.com/a/14831349/1402028
        return "https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout?continue=$dest";
    }

    public function generateUrl($route, $parameters = [])
    {
        // `login_url` is the URL prefix to use in the event that our site
        // is being reverse-proxied from a different domain (i.e., from the WAF)
        if ($this->params->has('login_url') && $this->params->get('login_url')) {
            $path = preg_replace('/\/$/', '', $this->params->get('login_url'));
            return $path . $this->urlGenerator->generate($route, $parameters);
        }
        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
