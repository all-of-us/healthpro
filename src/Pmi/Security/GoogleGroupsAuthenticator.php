<?php
namespace Pmi\Security;

use Pmi\Application\AbstractApplication;
use Pmi\Audit\Log;
use Pmi\HttpClient;
use Pmi\Util;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Authenticates by checking that the incoming Google user is a member of a
 * group in the Apps domain.
 */
class GoogleGroupsAuthenticator extends AbstractGuardAuthenticator
{
    const OAUTH_FAILURE_MESSAGE = 'OAuth Failure';

    private $app;
    
    public function __construct(AbstractApplication $app)
    {
        $this->app = $app;
    }
    
    private function getAuthLoginClient($state = null)
    {
        if ($state) {
            $client = new \Google_Client([
                'state' => $state
            ]);
        } else {
            $client = new \Google_Client();
        }
        $client->setHttpClient(new HttpClient());
        $client->setClientId($this->app->getConfig('auth_client_id'));
        $client->setClientSecret($this->app->getConfig('auth_client_secret'));

        $callbackUrl = $this->app->generateUrl('loginReturn', [], true);
        $client->setRedirectUri($callbackUrl);
        $client->setScopes(['email', 'profile']);
        return $client;
    }
    
    public function buildCredentials($googleUser)
    {
        // a user's credentials are effectively their logged-in Google user,
        // supplemented later checking their Google Groups
        return [
            'googleUser' => $googleUser
        ];
    }
    
    /** This runs on every request. */
    public function getCredentials(Request $request)
    {
        // if the user is already authenticated, then don't re-authenticate
        if ($request->getSession()->has('isLogin') && $this->app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->app['security.token_storage']->getToken()->getUser();
            $googleUser = $this->app->getGoogleUser();
            // make sure the user the Google user is the same as our user
            if ($googleUser && strcasecmp($googleUser->getEmail(), $user->getEmail()) === 0) {
                // firewall rules will pass and $this->start() will not be called
                return;
            } else {
                // force checkCredentials() to fail due to the mismatched users
                return $this->buildCredentials(null);
            }
        } elseif ($request->query->get('state') && $request->query->get('state') === $this->app['session']->get('auth_state')) {
            try {
                // process a return from Google authentication
                $client = $this->getAuthLoginClient();
                $token = $client->fetchAccessTokenWithAuthCode($request->query->get('code'));
                $client->setAccessToken($token);
                $idToken = $client->verifyIdToken();
                if ($idToken) {
                    $this->app['session']->set('googleUser', new \Pmi\Drc\GoogleUser($idToken['sub'], $idToken['email']));
                }
                return $this->buildCredentials($this->app->getGoogleUser());
            } catch (\Exception $e) {
                $this->app->logException($e);
                throw new AuthenticationException(self::OAUTH_FAILURE_MESSAGE);
            }
        } elseif ($this->app->getConfig('gae_auth') && $this->app->getGoogleUser()) {
            return $this->buildCredentials($this->app->getGoogleUser());
        } else {
            // firewall rules will fail and $this->start() will be called
            return;
        }
    }
    
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials['googleUser']->getEmail());
    }
    
    public function checkCredentials($credentials, UserInterface $user)
    {
        $validCredentials = is_array($credentials) && $credentials['googleUser'] &&
            // just a safeguard in case the Google user and our user get out of sync somehow
            strcasecmp($credentials['googleUser']->getEmail(), $user->getEmail()) === 0;

        if (!$this->app->isProd() && $this->app->getConfig('gaBypass')) {
            return $validCredentials; // Bypass groups auth
        } else {
            $valid2fa = !$this->app->getConfig('enforce2fa') || $user->hasTwoFactorAuth();
            return $validCredentials && count($user->getGroups()) > 0 && $valid2fa;
        }
    }
    
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $code = 403;
        $googleUser = $this->app->getGoogleUser();
        if ($googleUser) {
            $params = [
                'email' => $googleUser->getEmail(),
                'logoutUrl' => $this->app->getGoogleLogoutUrl()
            ];
            
            // attempt to load the user object for error msg customization
            try {
                $userProvider = new \Pmi\Security\UserProvider($this->app);
                $user = $userProvider->loadUserByUsername($googleUser->getEmail());
            } catch (\Exception $e) {
                $user = null;
            }
            
            // infer the reason behind the auth failure
            if ($user && $this->app->getConfig('enforce2fa') && !$user->hasTwoFactorAuth()) {
                $template = 'error-2fa.html.twig';
            } else {
                $template = 'error-auth.html.twig';
            }
        } elseif ($exception->getMessage() === self::OAUTH_FAILURE_MESSAGE) {
            $template = 'error-oauth.html.twig';
            $params = ['logoutUrl' => $this->app->getGoogleLogoutUrl()];
        } elseif ($this->app->isLocal() && $this->app->getConfig('gae_auth')) {
            $template = 'error-gae-auth.html.twig';
            $params = ['loginUrl' => $this->app->getGoogleLoginUrl('mockLogin')];
        } else {
            $template = $this->app['errorTemplate'];
            $params = ['code' => $code];
        }
        $this->app->log(Log::LOGIN_FAIL);
        // clear session in case Google user and our user are out of sync
        $this->app->logout();
        $response = new Response($this->app['twig']->render($template, $params), $code);
        $this->app->setHeaders($response);
        return $response;
    }
    
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // a flag to indicate that the user has just logged in
        $request->getSession()->set('isLogin', true);
        // has the user agreed to the system usage agreement this session?
        $request->getSession()->set('isUsageAgreed', false);
        
        if ($this->app->getConfig('gae_auth')) {
            // simulate the OAuth workflow for more accurate testing
            $this->app['session']->set('loginDestUrl', $request->getRequestUri());
            return $this->app->redirectToRoute('loginReturn');
        } else {
            // on success, let the request continue
            return;
        }
    }
    
    public function supportsRememberMe()
    {
        return false;
    }
    
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if ($this->app->getConfig('gae_auth')) {
            return $this->onAuthenticationFailure($request, $authException);
        } elseif (!$authException || $authException instanceof AuthenticationCredentialsNotFoundException) {
            $authState = sha1(openssl_random_pseudo_bytes(1024));
            $this->app['session']->set('auth_state', $authState);
            if ($request->attributes->get('_route') !== 'login' &&
                $request->attributes->get('_route') !== 'logout' &&
                $request->attributes->get('_route') !== 'loginReturn' &&
                $request->attributes->get('_route') !== 'timeout')
            {
                $this->app['session']->set('loginDestUrl', $request->getRequestUri());
            }
            $client = $this->getAuthLoginClient($authState);
            if (empty($this->app['session']->get('fromGoogleLogin')) && $request->attributes->get('_route') !== 'keepAlive') {
                $this->app['session']->set('fromGoogleLogin', true);
                return $this->app->redirect($this->app->getGoogleLogoutUrl());
            }
            return $this->app->redirect($client->createAuthUrl());
        } else {
            return $this->onAuthenticationFailure($request, $authException);
        }
    }
}
