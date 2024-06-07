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
            'clientId' => $params->get('salesforce_client_id'),
            'clientSecret' => $params->get('salesforce_client_secret'),
            'redirectUri' => $params->get('salesforce_redirect_uri'),
            'urlAuthorize' => $params->get('salesforce_url_authorize'),
            'urlAccessToken' => $params->get('salesforce_url_access_token'),
            'urlResourceOwnerDetails' => $params->get('salesforce_url_resource_owner_details'),
            'scopes' => $params->get('salesforce_scopes')
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
        return $this->params->get('salesforce_url_logout');
    }
}
