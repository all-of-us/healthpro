<?php

namespace App\Service;

use Pmi\Drc\GoogleUser;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Exception;
use Google_Client as GoogleClient;

class AuthService
{
    private $params;
    private $session;
    private $callbackUrl;

    public function __construct(
        ContainerBagInterface $params,
        SessionInterface $session,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->params = $params;
        $this->session = $session;
        $this->callbackUrl = $urlGenerator->generate('login_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
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

        return new GoogleUser($idToken['sub'], $idToken['email']);
    }
}
