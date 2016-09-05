<?php
namespace Pmi\Security;

use google\appengine\api\users\UserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Authenticates by checking that the incoming Google user is part of a
 * specified Google Apps domain.
 */
class GoogleAppsAuthenticator extends AbstractGuardAuthenticator
{
    private $app;
    private $gaDomain;
    private $googleUser;
    
    public function __construct(\Pmi\Application\AbstractApplication $app)
    {
        $this->app = $app;
        $this->gaDomain = isset($app['gaDomain']) ? $app['gaDomain'] : null;
        $this->googleUser = UserService::getCurrentUser();
    }
    
    public function getCredentials(Request $request)
    {
        if (!$this->googleUser) {
            return;
        }
        
        return [
            $this->googleUser->getEmail(),
            null
        ];
    }
    
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return new User($this->googleUser);
    }
    
    public function checkCredentials($credentials, UserInterface $user)
    {
        $userDomain = null;
        if ($this->googleUser && preg_match('/.*?@(.*)$/', $this->googleUser->getEmail(), $m)) {
            $userDomain = $m[1];
        }
        
        return $userDomain && $userDomain === $this->gaDomain;
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
