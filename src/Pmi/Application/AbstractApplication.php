<?php
namespace Pmi\Application;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Pmi\Audit\Log;
use Pmi\Datastore\DatastoreSessionHandler;
use Pmi\Monolog\StackdriverHandler;
use Pmi\Twig\Provider\TwigServiceProvider;
use Pmi\Session\SessionServiceProvider;
use Silex\Application;
use Silex\Provider\CsrfServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\Routing\LazyRequestMatcher;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Pmi\Service\MockUserService;

abstract class AbstractApplication extends Application
{
    const ENV_LOCAL = 'local'; // development environment (local GAE SDK)
    const ENV_DEV   = 'dev';   // development environment (deployed to GAE)
    const ENV_STAGING  = 'staging';  // staging environment
    const ENV_STABLE  = 'stable';  // security testing / training environment
    const ENV_PROD  = 'prod';  // production environment
    const DEFAULT_TIMEZONE = 'America/New_York';
    const DATASTORE_EMULATOR_HOST = 'localhost:8081';

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
    private function determineEnv()
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
        } elseif ($this->isPhpDevServer()) {
            return self::ENV_LOCAL;
        } else {
            throw new Exception("Bad environment: $env");
        }
    }

    public function __construct(array $values = array())
    {
        if (!array_key_exists('env', $values)) {
            $values['env'] = $this->determineEnv();
        }
        if (!array_key_exists('release', $values)) {
            $values['release'] = getenv('PMI_RELEASE') === false ?
                date('YmdHis') : getenv('PMI_RELEASE');
        }
        if (!array_key_exists('isUnitTest', $values)) {
            $values['isUnitTest'] = false;
        }
        if (!array_key_exists('debug', $values)) {
            $values['debug'] = ($values['env'] === self::ENV_LOCAL && !$values['isUnitTest']);
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

    public function isPhpDevServer()
    {
        return
            isset($_SERVER['SERVER_SOFTWARE']) &&
            preg_match('/^PHP [0-9\\.]+ Development Server$/', $_SERVER['SERVER_SOFTWARE']);
    }

    public function getName()
    {
        return $this->name;
    }

    public function setup($config = [])
    {
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

        $this->loadConfiguration($config);

        $this->register(new LocaleServiceProvider());
        $this->register(new TranslationServiceProvider(), [
            'locale_fallbacks' => ['en'],
        ]);

        // Register logging service
        $this->register(new MonologServiceProvider(), [
            // adjust 400 errors to debug log level
            'monolog.exception.logger_filter' => $this->protect(function ($e) {
                if ($e instanceof HttpExceptionInterface && $e->getStatusCode() == 404) {
                    return Logger::DEBUG;
                } elseif ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                    return Logger::INFO;
                }
                return Logger::CRITICAL;
            })
        ]);
        // Add syslog handler
        $this->extend('monolog', function($monolog, $app) {
            if ($app->isLocal() && !$app['isUnitTest']) {
                $handler = new SyslogHandler(false, LOG_USER, Logger::INFO, true, LOG_PID|LOG_PERROR);
            } else {
                $handler = new SyslogHandler(false, LOG_USER, Logger::INFO);
            }
            $formatter = new LineFormatter("%message% %context% %extra%", null, true);
            $formatter->includeStacktraces();
            $formatter->ignoreEmptyContextAndExtra();
            $handler->setFormatter($formatter);
            $monolog->pushHandler($handler);
            return $monolog;
        });
        // Add custom Stackdriver handler
        $this->extend('monolog', function($monolog, $app) {
            if ($app->isLocal() && !$app->getConfig('local_stackdriver_logging')) {
                return $monolog;
            }
            if ($app['isUnitTest']) {
                return $monolog;
            }

            $clientConfig = [];
            if ($app->isLocal()) {
                // Reuse service account key used for RDR auth
                $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/rdr_key.json';
                $clientConfig = [
                    'keyFilePath' => $keyFile
                ];
            }
            $handler = new StackdriverHandler($clientConfig, Logger::INFO);
            $handlerBuffer = new BufferHandler($handler, 0, Logger::INFO);
            $handlerBuffer->pushProcessor(function ($record) use ($app) {
                $request = $app['request_stack']->getCurrentRequest();
                $siteMetaData = $app->getLogMetaData();
                $record['extra']['labels'] = [
                    'user' => $siteMetaData['user'],
                    'site' => $siteMetaData['site'],
                    'ip' => $siteMetaData['ip']
                ];
                if ($request) {
                    $record['extra']['labels']['requestMethod'] = $request->getMethod();
                    $record['extra']['labels']['requestUrl'] = $request->getPathInfo();
                    if ($traceHeader = $request->headers->get('X-Cloud-Trace-Context')) {
                        $record['extra']['trace_header'] = $traceHeader;
                    }
                }
                return $record;
            });
            $monolog->pushHandler($handlerBuffer);
            return $monolog;
        });
        // Override routing.listener to disable routing info logging (see RoutingServiceProvider)
        $this['routing.listener'] = function ($app) {
            $urlMatcher = new LazyRequestMatcher(function () use ($app) {
                return $app['request_matcher'];
            });
            return new RouterListener($urlMatcher, $app['request_stack'], $app['request_context'], null, null, isset($app['debug']) ? $app['debug'] : false);
        };

        // Register Form service
        $this->register(new CsrfServiceProvider());
        $this->register(new FormServiceProvider());
        $this->register(new ValidatorServiceProvider());

        if (!$this['isUnitTest']) {
            $this->register(new SessionServiceProvider());
            if (isset($this['sessionHandler']) && $this['sessionHandler'] === 'datastore') {
                $this['session.storage.handler'] = new DatastoreSessionHandler();
            }
        }

        $this->registerCache();

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

    abstract public function canMockLogin();

    abstract public function getSiteId();

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

    /** For local development. */
    public function setMockUser($email)
    {
        MockUserService::switchCurrentUser($email);
        $this['session']->set('mockUser', MockUserService::getCurrentUser());
    }

    public function getGoogleUser()
    {
        if ($this->canMockLogin()) {
            if ($this['isUnitTest']) {
                return MockUserService::getCurrentUser();
            } else {
                return $this['session']->get('mockUser');
            }
        } else {
            return $this['session']->get('googleUser');
        }
    }

    public function getGoogleLogoutUrl($route = 'home')
    {
        $dest = $this->generateUrl($route, [], true);

        if ($this->isLocal() && $this->getConfig('local_mock_auth')) {
            return $this['isUnitTest'] ? null : $dest;
        } else {
            // http://stackoverflow.com/a/14831349/1402028
            return "https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout?continue=$dest";
        }
    }

    public function getUser()
    {
        $token = $this['security.token_storage']->getToken();
        if ($token && is_object($token->getUser())) {
            return $token->getUser();
        }
        return null;
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

    public function getUserEmail()
    {
        if ($user = $this->getUser()) {
            if (($info = $user->getInfo()) && isset($info['email'])) {
                return $info['email'];
            }
        }
        return null;
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
        $route = $request->attributes->get('_route');
        return (in_array($route, [
            'logout',
            'loginReturn',
            'timeout',
            'keepAlive',
            'clientTimeout',
            'agreeUsage'
        ]) || strpos($route, 'cron_') === 0);
    }

    protected function getBasePath()
    {
        $basePath = $this['request_stack']->getCurrentRequest()->getBasepath();
        if ($basePath === '/web') {
            // The combination of GAE's routing handlers and the Symfony Request object
            // base path logic results in an incorrect basepath for requests that start
            // with /web because the prefix is the same as the web root's directory name.
            // To account for this, we clear the basePath if it is "/web"
            $basePath = '';
        }

        return $basePath;
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
            // If not in debug mode or error is < 500, render the error template
            if (isset($this['errorTemplate']) && (!$this['debug'] || $code < 500)) {
                return $this['twig']->render($this['errorTemplate'], ['code' => $code]);
            } else {
                return;
            }
        });

        // Register custom Twig asset function
        $this['twig']->addFunction(new Twig_SimpleFunction('asset', function($asset) {
            $path = $this->getBasePath();
            $path .= '/assets/';
            return $path . ltrim($asset, '/');
        }));

        // Register custom webpack entrypoint function
        $this['twig']->addFunction(new Twig_SimpleFunction('webpack_entry', function($entry, $type) {
            $dir = $this['webpackBuildDirectory'];
            $entrypointsFile = $dir . '/entrypoints.json';
            if (!file_exists($entrypointsFile)) {
                $this['logger']->error('Missing entrypoints.json file');
                return;
            }
            $entrypoints = json_decode(file_get_contents($entrypointsFile));
            if (!isset($entrypoints->entrypoints) || !isset($entrypoints->entrypoints->{$entry}) || !isset($entrypoints->entrypoints->{$entry}->{$type})) {
                $this['logger']->error("Entry for {$entry}.{$type} not found");
                return;
            }
            $entries = $entrypoints->entrypoints->{$entry}->{$type};

            // for view-specific js, ignore entries already included in app.js
            if ($type === 'js' && $entry !== 'app' && isset($entrypoints->entrypoints->app->js)) {
                $entries = array_diff($entries, $entrypoints->entrypoints->app->js);
            }
            $html = '';
            foreach ($entries as $entry) {
                switch ($type) {
                    case 'css':
                        $html .= "<link rel=\"stylesheet\" href=\"{$entry}\">\n";
                        break;
                    case 'js':
                        $html .= "<script src=\"{$entry}\"></script>\n";
                        break;
                    default:
                        $this['logger']->error("Unsupported webpack entry type: {$entry}.{$type}");
                }
            }
            return $html;
        }, ['is_safe' => ['html']]));

        // Register custom Twig path_exists function
        $this['twig']->addFunction(new Twig_SimpleFunction('path_exists', function ($name) {
            return !is_null($this['routes']->get($name));
        }));

        // Convert a string into a slug
        $this['twig']->addFilter(new Twig_SimpleFilter('slugify', function ($text) {
            $output = trim(strtolower($text));
            $output = preg_replace('/[^a-z0-9]/', '-', $output);
            return $output;
        }));

        /**
         * Display Message
         *
         * Register custom Twig function for in-app messaging
         *
         * @param string $name Reference to use within template, without the configuration prefix
         * @param string|false $type Type of message; determines how it is rendered
         *                     - false - Render contents inline
         *                     - alert - Bootstrap "info" alert
         *                     - tooltip - Information icon with tooltip
         * @param array $options Options for the message
         *              - closeButton (boolean) - Display a close button for 'alert' type
         * @return string
         */
        $this['twig']->addFunction(new Twig_SimpleFunction('display_message', function($name, $type = false, $options = []) {
            $configPrefix = 'messaging_';
            $message = $this->getConfig($configPrefix . $name);
            if (empty($message)) {
                return;
            }
            switch ($type) {
                case 'alert':
                    if (isset($options['closeButton']) && $options['closeButton']) {
                        $message .= ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                    }
                    return '<div class="alert alert-info">' . $message . '</div>';
                    break;
                case 'tooltip':
                    $tooltipText = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    return '<span title="' . $tooltipText . '" data-toggle="tooltip" data-container="body"><i class="fa fa-info-circle" aria-hidden="true"></i></span>';
                    break;
                default:
                    return $message;
            }
        }, ['is_safe' => ['html']]));

        // Register custom Twig cache
        if (isset($this['twigCacheHandler']) && $this['twigCacheHandler'] === 'file') {
            $this['twig']->setCache(new \Twig_Cache_Filesystem($this['twigCacheDirectory']));
        }
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
        if (!$this['isUnitTest'] && !$this->isPhpDevServer() && $action != Log::REQUEST) {
            $log->logDatastore();
        }
    }

    public function logException(\Exception $exception)
    {
        $this['logger']->critical('Caught Exception', ['exception' => $exception]);
    }

    /**
     * Identical to the built-in json method, but uses json_encode's pretty print option.
     */
    public function jsonPrettyPrint($data = array(), $status = 200, array $headers = array())
    {
        $response = new JsonResponse($data, $status, $headers);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }

    public function getTimeZones()
    {
        return self::$timezoneOptions;
    }

    public function registerCache()
    {
        $this['cache'] = new \Pmi\Cache\DatastoreAdapter($this->getConfig('ds_clean_up_limit'));
        $this['cache']->setLogger($this['logger']);
    }

    public function getLogMetaData()
    {
        $user = $site = $ip = null;

        try {
            if (($userObj = $this->getUser()) && is_object($userObj)) {
                $user = $userObj->getUsername();
            } elseif ($userObj = $this->getGoogleUser()) {
                $user = $userObj->getEmail();
            }
        } catch (Exception $e) {
        }

        try {
            $site = $this->getSiteId();
        } catch (Exception $e) {
        }

        try {
            if ($request = $this['request_stack']->getCurrentRequest()) {
                // http://symfony.com/doc/3.4/deployment/proxies.html#but-what-if-the-ip-of-my-reverse-proxy-changes-constantly
                $trustedProxies = ['127.0.0.1', $request->server->get('REMOTE_ADDR')];
                $originalTrustedProxies = Request::getTrustedProxies();
                $originalTrustedHeaderSet = Request::getTrustedHeaderSet();
                // specififying HEADER_X_FORWARDED_FOR because App Engine 2nd Gen also adds a FORWARDED
                Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);

                // getClientIps reverses the order, so we want the last ip which will be the user's origin ip
                $ips = $request->getClientIps();
                $ip = array_pop($ips);

                // reset trusted proxies
                Request::setTrustedProxies($originalTrustedProxies, $originalTrustedHeaderSet);

                // identify cron user
                if ($user === null && $request->headers->get('X-Appengine-Cron') === 'true') {
                    $user = 'Appengine-Cron';
                }
            }
        } catch (Exception $e) {
        }

        return [
            'user' => $user,
            'site' => $site,
            'ip' => $ip
        ];
    }
}
