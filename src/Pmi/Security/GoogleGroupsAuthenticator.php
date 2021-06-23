<?php
namespace Pmi\Security;

use App\EventListener\ResponseSecurityHeadersTrait;
use Pmi\Application\AbstractApplication;
use Pmi\Audit\Log;
use Pmi\HttpClient;
use Pmi\Service\UserService;
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
    use ResponseSecurityHeadersTrait;

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

    public function supports(Request $request)
    {
        // if the user is already authenticated, then don't re-authenticate
        if ($request->getSession()->has('isLogin') &&
            !empty($this->app['security.token_storage']->getToken()) &&
            $this->app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->app['security.token_storage']->getToken()->getUser();
            $googleUser = $this->app->getGoogleUser();
            // make sure the user the Google user is the same as our user
            if ($googleUser && strcasecmp($googleUser->getEmail(), $user->getEmail()) === 0) {
                // firewall rules will pass and $this->start() will not be called
                return false;
            } else {
                // force checkCredentials() to fail due to the mismatched users
                return true;
            }
        } elseif ($request->query->get('state') && $request->query->get('state') === $this->app['session']->get('auth_state')) {
            return true;
        } elseif ($this->app->getConfig('local_mock_auth') && $this->app->getGoogleUser()) {
            return true;
        } else {
            // firewall rules will fail and $this->start() will be called
            return false;
        }
    }

    /** This runs on every request. */
    public function getCredentials(Request $request)
    {
        // if the user is already authenticated, then don't re-authenticate
        if ($request->getSession()->has('isLogin') &&
            !empty($this->app['security.token_storage']->getToken()) &&
            $this->app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user = $this->app['security.token_storage']->getToken()->getUser();
            $googleUser = $this->app->getGoogleUser();
            // make sure the user the Google user is the same as our user
            if ($googleUser && strcasecmp($googleUser->getEmail(), $user->getEmail()) === 0) {
                // firewall rules will pass and $this->start() will not be called
                return '';
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
        } elseif ($this->app->getConfig('local_mock_auth') && $this->app->getGoogleUser()) {
            return $this->buildCredentials($this->app->getGoogleUser());
        } else {
            // firewall rules will fail and $this->start() will be called
            return '';
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
        return $this->app->redirect('s/login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // a flag to indicate that the user has just logged in
        $request->getSession()->set('isLogin', true);
        // has the user agreed to the system usage agreement this session?
        $request->getSession()->set('isUsageAgreed', false);
        // Update last login for user record
        UserService::updateLastLogin($this->app);
        if ($this->app->canMockLogin()) {
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
        if ($this->app->canMockLogin()) {
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
            return $this->app->redirect('s/login');
        } else {
            return $this->onAuthenticationFailure($request, $authException);
        }
    }
}
