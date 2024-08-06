<?php

namespace App\Service;

use App\Drc\SalesforceUser;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SalesforceAuthService
{
    private RequestStack $requestStack;
    private ?GenericProvider $provider = null;
    private ContainerBagInterface $params;
    private EnvironmentService $env;

    public function __construct(
        RequestStack $requestStack,
        ContainerBagInterface $params,
        EnvironmentService $env
    ) {
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->env = $env;
    }

    public function getAuthorizationUrl(): ?string
    {
        return $this->getProvider()->getAuthorizationUrl();
    }

    public function getAccessToken($code): AccessTokenInterface|AccessToken
    {
        return $this->getProvider()->getAccessToken('authorization_code', ['code' => $code]);
    }

    public function getResourceOwner($token): ResourceOwnerInterface
    {
        return $this->getProvider()->getResourceOwner($token);
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
        $ppscEnv = $this->env->getPpscEnv($this->requestStack->getSession()->get('ppscEnv'));
        return $this->params->has($ppscEnv . '_' . $field) ? $this->params->get($ppscEnv . '_' . $field) : null;
    }

    private function getProvider(): GenericProvider
    {
        if ($this->provider === null) {
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
        return $this->provider;
    }
}
