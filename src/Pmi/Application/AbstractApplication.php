<?php
namespace Pmi\Application;

use Exception;
use Memcache;
use Pmi\Audit\Log;
use Pmi\Datastore\DatastoreSessionHandler;
use Pmi\Twig\Provider\TwigServiceProvider;
use Silex\Application;
use Silex\Provider\CsrfServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

abstract class AbstractApplication extends Application
{
    const ENV_LOCAL = 'local'; // development environment (local GAE SDK)
    const ENV_DEV   = 'dev';   // development environment (deployed to GAE)
    const ENV_STAGING  = 'staging';  // staging environment
    const ENV_STABLE  = 'stable';  // security testing / training environment
    const ENV_PROD  = 'prod';  // production environment
    const DEFAULT_TIMEZONE = 'America/New_York';

    protected $name;
    protected $configuration = [];

    public static $timezoneOptions = [
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Mountain Time - Arizona',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time'
    ];

    /** Determines the environment under which the code is running. */
    private static function determineEnv()
    {
        $env = getenv('PMI_ENV');
        if ($env == self::ENV_LOCAL) {
            return self::ENV_LOCAL;
        } elseif ($env == self::ENV_DEV) {
            return self::ENV_DEV;
        } elseif ($env == self::ENV_STABLE) {
            return self::ENV_STABLE;
        } elseif ($env == self::ENV_STAGING) {
            return self::ENV_STAGING;
        } elseif ($env == self::ENV_PROD) {
            return self::ENV_PROD;
        } else {
            throw new Exception("Bad environment: $env");
        }
    }
    
    public function __construct(array $values = array())
    {
        if (!array_key_exists('env', $values)) {
            $values['env'] = self::determineEnv();
        }
        if (!array_key_exists('release', $values)) {
            $values['release'] = getenv('PMI_RELEASE') === false ?
                date('YmdHis') : getenv('PMI_RELEASE');
        }
        if (!array_key_exists('isUnitTest', $values)) {
            $values['isUnitTest'] = false;
        }
        if (!array_key_exists('debug', $values)) {
            $values['debug'] = ($values['env'] === self::ENV_PROD || $values['env'] === self::ENV_STAGING || $values['env'] === self::ENV_STABLE || $values['isUnitTest']) ? false : true;
        }
        $values['assetVer'] = $values['env'] === self::ENV_LOCAL ?
            date('YmdHis') : $values['release'];

        parent::__construct($values);
    }

    public function isLocal()
    {
        return $this['env'] === self::ENV_LOCAL;
    }

    public function isDev()
    {
        return $this['env'] === self::ENV_DEV;
    }

    public function isStable()
    {
        return $this['env'] === self::ENV_STABLE;
    }

    public function isStaging()
    {
        return $this['env'] === self::ENV_STAGING;
    }

    public function isProd()
    {
        return $this['env'] === self::ENV_PROD;
    }
    
    public function getName()
    {
        return $this->name;
    }

    public function setup($config = [])
    {
        // GAE SDK dev AppServer has a conflict with loading external XML entities
        // https://github.com/GoogleCloudPlatform/appengine-symfony-starter-project/blob/master/src/AppEngine/Environment.php#L52-L69
        if ($this->isLocal()) {
            libxml_disable_entity_loader(false);
        }
        
        // Register *early* before middleware
        if (method_exists($this, 'earlyBeforeCallback')) {
            $this->before([$this, 'earlyBeforeCallback'], Application::EARLY_EVENT);
        }
        // Register before middleware
        if (method_exists($this, 'beforeCallback')) {
            $this->before([$this, 'beforeCallback']);
        }
        // Register after middleware
        if (method_exists($this, 'afterCallback')) {
            $this->after([$this, 'afterCallback']);
        }
        // Register finish middleware
        if (method_exists($this, 'finishCallback')) {
            $this->finish([$this, 'finishCallback']);
        }

        $this->register(new LocaleServiceProvider());
        $this->register(new TranslationServiceProvider(), [
            'locale_fallbacks' => ['en'],
        ]);

        // Register Form service
        $this->register(new CsrfServiceProvider());
        $this->register(new FormServiceProvider());
        $this->register(new ValidatorServiceProvider());

        if (isset($this['sessionHandler'])) {
            switch ($this['sessionHandler']) {
                case 'memcache':
                    $this->enableMemcacheSession();
                    break;
                case 'datastore':
                    $this->enableDatastoreSession();
                    break;
            }
        }
        
        $this->loadConfiguration($config);
        
        // configure security and boot before enabling twig so that `is_granted` will be available
        $this->registerSecurity();
        $this->boot();

        // Register and configure Twig
        if (isset($this['templatesDirectory']) && $this['templatesDirectory']) {
            $this->enableTwig();
        }

        return $this;
    }

    /** Populates $this->configuration */
    abstract protected function loadConfiguration($override = []);
    
    public function getConfig($key)
    {
        if (isset($this->configuration[$key])) {
            return $this->configuration[$key];
        } else {
            return null;
        }
    }

    public function setConfig($key, $val)
    {
        $this->configuration[$key] = $val;
    }
    
    /** Sets up authentication and firewall. */
    abstract protected function registerSecurity();
    
    /** Returns an array of (unvalidated) IPs / CIDR blocks. */
    public function getIpWhitelist()
    {
        $list = [];
        $config = trim($this->getConfig('ip_whitelist'));
        if ($config) {
            $ips = explode(',', $config);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                $list[] = $ip;
            }
        }
        return $list;
    }
    
    public function getGoogleServiceClass()
    {
        return $this['isUnitTest'] ? 'Tests\Pmi\GoogleUserService' :
            'google\appengine\api\users\UserService';
    }
    
    public function getGoogleUser()
    {
        if ($this->getConfig('gae_auth')) {
            $cls = $this->getGoogleServiceClass();
            return class_exists($cls) ? $cls::getCurrentUser() : null;
        } else {
            return $this['session']->get('googleUser');
        }
    }
    
    public function getGoogleLogoutUrl($route = 'home')
    {
        $dest = $this->generateUrl($route, [], true);
        
        if ($this->getConfig('gae_auth')) {
            $cls = $this->getGoogleServiceClass();
            return class_exists($cls) ? $cls::createLogoutURL($dest) : null;
        } else {
            // http://stackoverflow.com/a/14831349/1402028
            return "https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout?continue=$dest";
        }
    }
    
    public function getGoogleLoginUrl($route = 'home')
    {
        $dest = $this->generateUrl($route, [], true);
        
        if ($this->getConfig('gae_auth')) {
            $cls = $this->getGoogleServiceClass();
            return class_exists($cls) ? $cls::createLoginURL($dest) : null;
        }
    }
    
    public function getUser()
    {
        $token = $this['security.token_storage']->getToken();
        return $token ? $token->getUser() : null;
    }

    public function getUserId()
    {
        if ($user = $this->getUser()) {
            return $user->getId();
        } else {
            return null;
        }
    }

    public function getUserTimezone($useDefault = true)
    {
        if ($user = $this->getUser()) {
            if (($info = $user->getInfo()) && isset($info['timezone'])) {
                return $info['timezone'];
            }
        }
        if ($useDefault) {
            return self::DEFAULT_TIMEZONE;
        } else {
            return null;
        }
    }

    public function getUserTimezoneDisplay()
    {
        $timezone = $this->getUserTimezone();
        if (array_key_exists($timezone, static::$timezoneOptions)) {
            return static::$timezoneOptions[$timezone];
        } else {
            return $timezone;
        }
    }

    public function hasRole($role)
    {
        return $this->isLoggedIn() && $this['security.authorization_checker']->isGranted($role);
    }
    
    /** Is the user's session expired? */
    public function isLoginExpired()
    {
        $time = time();
        // custom "last used" session time updated on keepAliveAction
        $idle = $time - $this['session']->get('pmiLastUsed', $time);
        $remaining = $this['sessionTimeout'] - $idle;
        return $this->isLoggedIn() && $remaining <= 0;
    }
    
    public function isLoggedIn()
    {
        return $this['security.token_storage']->getToken() && $this['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY');
    }
    
    /**
     * "Upkeep" routes are routes that we typically want to allow through
     * even when workflow dictates otherwise.
     */
    public function isUpkeepRoute(Request $request)
    {
        return $request->attributes->get('_route') === 'logout' ||
            $request->attributes->get('_route') === 'loginReturn' ||
            $request->attributes->get('_route') === 'timeout' ||
            $request->attributes->get('_route') === 'keepAlive' ||
            $request->attributes->get('_route') === 'clientTimeout' ||
            $request->attributes->get('_route') === 'agreeUsage';
    }

    protected function enableTwig()
    {
        $options = [
            'twig.path' => $this['templatesDirectory'],
            'twig.form.templates' => ['bootstrap_3_layout.html.twig']
        ];

        // Register Twig service
        $this->register(new TwigServiceProvider(), $options);

        // Set error callback using error template
        $this->error(function (Exception $e, $request, $code) {
            // run application-specific error callback
            if (method_exists($this, 'onErrorCallback')) {
                $response = $this->onErrorCallback($e, $code);
                if ($response) {
                    return $response;
                }
            }
            
            if (isset($this['errorTemplate']) && (!$this['debug'] || $code < 500)) {
                // so we have a way of viewing production exceptions
                if ($code >= 500) {
                    error_log($e);
                }
                
                if ($e instanceof AccessDeniedHttpException && $code === 403) {
                    // display custom page if being denied due to IP whitelist
                    $ips = $this->getIpWhitelist();
                    if (is_array($ips) && count($ips) > 0 && !IpUtils::checkIp($request->getClientIp(), $ips)) {
                        $this->log(Log::INVALID_IP);
                        return $this['twig']->render('error-ip.html.twig', ['code' => $code]);
                    }
                }
                
                return $this['twig']->render($this['errorTemplate'], ['code' => $code]);
            } else {
                return;
            }
        });

        // Register custom Twig asset function
        $this['twig']->addFunction(new Twig_SimpleFunction('asset', function($asset) {
            $basePath = $this['request_stack']->getCurrentRequest()->getBasepath();
            $basePath .= '/assets/';
            return $basePath . ltrim($asset, '/');
        }));

        // Register custom Twig path_exists function
        $this['twig']->addFunction(new Twig_SimpleFunction('path_exists', function($name) {
            return !is_null($this['routes']->get($name));
        }));

        // Register custom Twig display_code filter
        $this['twig']->addFilter(new Twig_SimpleFilter('display_code', function($text) {
            return ucwords(strtolower(str_replace('_', ' ', $text)));
        }));

        // Register custom Twig cache
        if (isset($this['twigCacheHandler'])) {
            switch ($this['twigCacheHandler']) {
                case 'memcache':
                    if (class_exists('Memcache')) {
                        $this['twig']->setCache(new \Pmi\Twig\Cache\Memcache());
                    }
                    break;
                case 'file':
                    if (isset($this['cacheDirectory'])) {
                        $this['twig']->setCache(new \Twig_Cache_Filesystem($this['cacheDirectory'] . '/twig'));
                    }
                    break;
            }
        }
    }

    protected function enableMemcacheSession()
    {
        $this->register(new SessionServiceProvider());
        $memcache = new Memcache();
        $handler = new MemcacheSessionHandler($memcache);
        $this['session.storage.handler'] = $handler;
    }

    protected function enableDatastoreSession()
    {
        $this->register(new SessionServiceProvider());
        $this['session.storage.handler'] = new DatastoreSessionHandler();
    }
    
    public function logout()
    {
        $this['security.token_storage']->setToken(null);
        $this['session']->invalidate();
    }

    public function generateUrl($route, $parameters = [], $absolute = false)
    {
        if ($this->getName()) {
            $route = $this->getName() . '_' . $route;
        }
        
        if ($absolute) {
            // `login_url` is the URL prefix to use in the event that our site
            // is being reverse-proxied from a different domain (i.e., from the WAF)
            if ($this->getConfig('login_url')) {
                $path = preg_replace('/\/$/', '', $this->getConfig('login_url'));
                return $path . $this['url_generator']->generate($route, $parameters);
            } else {
                return $this['url_generator']->generate($route, $parameters, \Symfony\Component\Routing\Generator\UrlGenerator::ABSOLUTE_URL);
            }
        } else {
            return $this['url_generator']->generate($route, $parameters);
        }
    }

    public function redirectToRoute($route, $parameters = [])
    {
        return $this->redirect($this->generateUrl($route, $parameters));
    }
    
    public function forwardToRoute($route, Request $request)
    {
        $subRequest = Request::create($this->generateUrl($route), 'GET', $request->request->all(), $request->cookies->all(), array(), $request->server->all());
        if ($request->getSession()) {
            $subRequest->setSession($request->getSession());
        }
        return $this->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
    }

    public function addFlash($type, $value)
    {
        $request = $this['request_stack']->getCurrentRequest();
        $request->getSession()->getFlashBag()->add($type, $value);
    }

    public function addFlashError($string, array $translationParams = [])
    {
        $string = $this['translator']->trans($string, $translationParams);
        $this->addFlash('error', $string);
    }

    public function addFlashNotice($string, array $translationParams = [])
    {
        $string = $this['translator']->trans($string, $translationParams);
        $this->addFlash('notice', $string);
    }
    
    public function addFlashSuccess($string, array $translationParams = [])
    {
        $string = $this['translator']->trans($string, $translationParams);
        $this->addFlash('success', $string);
    }

    public function log($action, $data = null)
    {
        $log = new Log($this, $action, $data);
        $log->logSyslog();
        if (!$this['isUnitTest'] && $action != Log::REQUEST) {
            $log->logDatastore();
        }
    }
}
