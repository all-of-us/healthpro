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
        $googleUser = $this->app->getGoogleUser();
        
        // if the user is already authenticated, then don't re-authenticate
        $hasLogin = $request->getSession()->has('isLogin');
        if ($hasLogin && $this->app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->app['security.token_storage']->getToken()->getUser();
            // make sure the user is still logged into Google and has not switched their account somehow
            if ($googleUser && strcasecmp($googleUser->getEmail(), $user->getEmail()) === 0) {
                // firewall rules will pass and $this->start() will not be called
                return;
            } else {
                // force checkCredentials() to fail due to the mismatched users
                return $this->buildCredentials(null);
            }
        }
        
        // if the user is not logged into Google, then they are not credentialed
        if (!$googleUser) {
            // firewall rules will fail and $this->start() will be called
            // (though if all GAE handlers require google login, we will never reach this point)
            return;
        }
        
        return $this->buildCredentials($googleUser);
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
            $params = [
                'email' => $googleUser->getEmail(),
                'logoutUrl' => $this->app->getGoogleLogoutUrl()
            ];
            $this->app->log(Log::LOGIN_FAIL);
            // clear session in case Google user and our user are out of sync
            $this->app->logout();
            $response = new Response($this->app['twig']->render('error-auth.html.twig', $params), $code);
            $this->app->setHeaders($response);

            return $response;
        } else {
            return $this->app->redirectToRoute('login');
        }
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
        // we never start authentication in our app because Google handles that,
        // so any call to this method implies an auth failure
        return $this->onAuthenticationFailure($request, $authException);
    }
}
