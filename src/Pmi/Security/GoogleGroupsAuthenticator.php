<?php
namespace Pmi\Security;

use Pmi\Application\AbstractApplication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Authenticates by checking that the incoming Google user is a member of a
 * group in the Apps domain.
 */
class GoogleGroupsAuthenticator extends AbstractGuardAuthenticator
{
    private $app;
    
    public function __construct(AbstractApplication $app)
    {
        $this->app = $app;
    }
    
    public function getCredentials(Request $request)
    {
        $googleUser = $this->app->getGoogleUser();
        if (!$googleUser) {
            return;
        }
        
        return [
            $googleUser->getEmail(),
            null
        ];
    }
    
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials[0]);
    }
    
    public function checkCredentials($credentials, UserInterface $user)
    {
        // user must be logged in to their Google account and be a member of
        // at least one Group to authenticate
        return is_array($credentials) && count($user->getGroups()) > 0 &&
            // just a safeguard in case the Google user and our user get out of sync somehow
            strcasecmp($credentials[0], $user->getEmail()) === 0;
    }
    
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $code = 403;
        $response = new Response($this->app['twig']->render($this->app['errorTemplate'], ['code' => $code]), $code);
        $this->app->setHeaders($response);
        return $response;
    }
    
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return;
    }
    
    public function supportsRememberMe()
    {
        return false;
    }
    
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = array(
            // you might translate this message
            'message' => 'Authentication Required',
        );

        return new JsonResponse($data, 401);
    }
}
