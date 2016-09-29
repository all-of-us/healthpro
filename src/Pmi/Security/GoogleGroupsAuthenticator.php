<?php
namespace Pmi\Security;

use Pmi\Application\AbstractApplication;
use Pmi\Audit\Log;
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
    private $app;
    
    /** Array of whitelisted IPs, or null if configuration error. */
    private $ipWhitelist;
    
    public function __construct(AbstractApplication $app)
    {
        $this->app = $app;
        $this->ipWhitelist = $this->buildIpWhitelist();
    }
    
    private function buildIpWhitelist()
    {
        $list = [];
        $config = $this->app->getConfig('ip_whitelist');
        if ($config) {
            $ips = explode(',', $config);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $list[$ip] = $ip;
                } else {
                    return null;
                }
            }
        }
        return $list;
    }
    
    public function getIpWhitelist()
    {
        return $this->ipWhitelist;
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
        $client->setClientId($this->app->getConfig('auth_client_id'));
        $client->setClientSecret($this->app->getConfig('auth_client_secret'));
        // http://stackoverflow.com/a/33838098/1402028
        if ($this->app->isDev()) {
            $client->setHttpClient(new \GuzzleHttp\Client(['verify'=>false]));
        }

        // `login_url` is the URL prefix to use in the event that our site
        // is being reverse-proxied from a different domain (i.e., from the WAF)
        if ($this->app->getConfig('login_url')) {
            $path = preg_replace('/\/$/', '', $this->app->getConfig('login_url'));
            $callbackUrl = $path . $this->app['url_generator']->generate('loginReturn');
        } else {
            $callbackUrl = $this->app['url_generator']->generate('loginReturn', [], \Symfony\Component\Routing\Generator\UrlGenerator::ABSOLUTE_URL);
        }
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
            // process a return from Google authentication
            $client = $this->getAuthLoginClient();
            $token = $client->fetchAccessTokenWithAuthCode($request->query->get('code'));
            $client->setAccessToken($token);
            $idToken = $client->verifyIdToken();
            if ($idToken) {
                $this->app['session']->set('googleUser', new \Pmi\Drc\GoogleUser($idToken['sub'], $idToken['email']));
            }
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

        if ($this->app->isDev() && $this->app->getConfig('gaBypass')) {
            return $validCredentials; // Bypass groups auth
        } else {
            return $validCredentials && count($user->getGroups()) > 0;
        }
    }
    
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $code = 403;
        $googleUser = $this->app->getGoogleUser();
        if ($googleUser) {
            $template = 'error-auth.html.twig';
            $params = [
                'email' => $googleUser->getEmail(),
                'logoutUrl' => $this->app->getGoogleLogoutUrl()
            ];
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
        // on success, let the request continue
        return;
    }
    
    public function supportsRememberMe()
    {
        return false;
    }
    
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if (!$authException || $authException instanceof AuthenticationCredentialsNotFoundException) {
            $authState = sha1(openssl_random_pseudo_bytes(1024));
            $this->app['session']->set('auth_state', $authState);
            $client = $this->getAuthLoginClient($authState);
            return $this->app->redirect($client->createAuthUrl());
        } else {
            return $this->onAuthenticationFailure($request, $authException);
        }
    }
}
