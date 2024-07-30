<?php

namespace App\Service;

use App\Drc\SalesforceUser;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SalesforceAuthService
{
    private RequestStack $requestStack;
    private GenericProvider $provider;
    private ContainerBagInterface $params;
    private SessionInterface $session;
    private EnvironmentService $env;

    public function __construct(
        RequestStack $requestStack,
        ContainerBagInterface $params,
        SessionInterface $session,
        EnvironmentService $env
    ) {
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->session = $session;
        $this->env = $env;
        $this->provider = new GenericProvider([
            'clientId' => $this->getParams('salesforce_client_id'),
            'clientSecret' => $this->getParams('salesforce_client_secret'),
            'redirectUri' => $this->getParams('salesforce_redirect_uri'),
            'urlAuthorize' => $this->getParams('salesforce_url_authorize'),
            'urlAccessToken' => $this->getParams('salesforce_url_access_token'),
            'urlResourceOwnerDetails' => $this->getParams('salesforce_url_resource_owner_details'),
            'scopes' => $this->getParams('salesforce_scopes')
        ]);
    }

    public function getAuthorizationUrl(): ?string
    {
        return $this->provider->getAuthorizationUrl();
    }

    public function getAccessToken($code): AccessTokenInterface|AccessToken
    {
        return $this->provider->getAccessToken('authorization_code', ['code' => $code]);
    }

    public function getResourceOwner($token): ResourceOwnerInterface
    {
        return $this->provider->getResourceOwner($token);
    }

    public function processAuth($credentials): SalesforceUser
    {
        // Exchange the authorization code for an access token
        $accessToken = $this->getAccessToken($credentials);

        // Get user details from the OIDC provider
        $resourceOwner = $this->getResourceOwner($accessToken);
        $userDetails = $resourceOwner->toArray();
        $user = new SalesforceUser($userDetails['user_id'], $userDetails['email'], $userDetails['zoneinfo'] ?? null);
        $this->requestStack->getSession()->set('salesforceUser', $user);

        return $user;
    }

    private function getParams($field): string|null
    {
        $ppscEnv = $this->env->getPpscEnv($this->session->get('ppscEnv'));
        return $this->params->has($ppscEnv . '_' . $field) ? $this->params->get($ppscEnv . '_' . $field) : null;
    }
}
