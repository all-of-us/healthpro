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
    private GenericProvider $provider;
    private ContainerBagInterface $params;

    public function __construct(RequestStack $requestStack, ContainerBagInterface $params)
    {
        $this->requestStack = $requestStack;
        $this->params = $params;
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
        $user = new SalesforceUser($userDetails['user_id'], $userDetails['email']);
        $this->requestStack->getSession()->set('salesforceUser', $user);

        return $user;
    }

    public function getLogoutUrl(): string
    {
        return $this->getParams('salesforce_url_logout');
    }

    private function getParams($field): string|null
    {
        return $this->params->has($field) ? $this->params->get($field) : null;
    }
}
