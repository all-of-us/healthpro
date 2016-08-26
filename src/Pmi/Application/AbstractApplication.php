<?php
namespace Pmi\Application;

use Exception;
use Memcache;
use Pmi\Datastore\DatastoreSessionHandler;
use Pmi\Twig\TwigMemcache;
use Silex\Application;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig_SimpleFunction;

abstract class AbstractApplication extends Application
{
    const ENV_DEV  = 'dev';  // development environment (local GAE SDK)
    const ENV_TEST = 'test'; // testing environment (GAE test projects)
    const ENV_PROD = 'prod'; // production environment
    
    protected $name;

    /** Determines the environment under which the code is running. */
    private static function determineEnv()
    {
        $env = getenv('PMI_ENV');
        if ($env == self::ENV_DEV) {
            return self::ENV_DEV;
        } elseif ($env == self::ENV_TEST) {
            return self::ENV_TEST;
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
        if (!array_key_exists('debug', $values)) {
            $values['debug'] = $values['env'] === self::ENV_PROD ? false : true;
        }
        if (!array_key_exists('isUnitTest', $values)) {
            $values['isUnitTest'] = false;
        }
        $values['assetVer'] = $values['env'] === self::ENV_DEV ?
            date('YmdHis') : $values['release'];
        
        parent::__construct($values);
    }
    
    public function isDev()
    {
        return $this['env'] === self::ENV_DEV;
    }
    
    public function isTest()
    {
        return $this['env'] === self::ENV_TEST;
    }
    
    public function isProd()
    {
        return $this['env'] === self::ENV_PROD;
    }
    
    public function getName()
    {
        return $this->name;
    }

    public function setup()
    {
        // GAE SDK dev AppServer has a conflict with loading external XML entities
        // https://github.com/GoogleCloudPlatform/appengine-symfony-starter-project/blob/master/src/AppEngine/Environment.php#L52-L69
        if ($this->isDev()) {
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
            $this->after([$this, 'finishCallback']);
        }

        $this->register(new LocaleServiceProvider());
        $this->register(new TranslationServiceProvider(), [
            'locale_fallbacks' => ['en'],
        ]);

        // Register Form service
        $this->register(new FormServiceProvider());
        $this->register(new ValidatorServiceProvider());

        // Register and configure Twig
        if (isset($this['templatesDirectory']) && $this['templatesDirectory']) {
            $this->enableTwig();
        }

        // Configure Memcache session handler
        if (isset($this['memcacheSession']) && $this['memcacheSession']) {
            $this->enableMemcacheSession();
        }

        // Configure Datastore session handler
        if (isset($this['datastoreSession']) && $this['datastoreSession']) {
            $this->enableDatastoreSession();
        }

        return $this;
    }

    protected function enableTwig()
    {
        // Register Twig service
        $this->register(new TwigServiceProvider(), [
            'twig.path' => $this['templatesDirectory'],
            'twig.form.templates' => ['bootstrap_3_layout.html.twig']
        ]);
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

        // Register custom Twig Memcache cacher
        if (class_exists('Memcache') && class_exists('TwigMemcache')) {
            $this['twig']->setCache(new TwigMemcache());
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

    public function generateUrl($route, $parameters = [])
    {
        if ($this->getName()) {
            $route = $this->getName() . '_' . $route;
        }
        return $this['url_generator']->generate($route, $parameters);
    }

    public function redirectToRoute($route, $parameters = [])
    {
        return $this->redirect($this->generateUrl($route, $parameters));
    }
    
    public function forwardToRoute($route, $request)
    {
        $subRequest = Request::create($this->generateUrl($route), 'GET', array(), $request->cookies->all(), array(), $request->server->all());
        if ($request->getSession()) {
            $subRequest->setSession($request->getSession());
        }
        return $this->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
    }

    public function addFlash(Request $request, $type, $value)
    {
        $request->getSession()->getFlashBag()->add($type, $value);
    }

    public function addFlashError(Request $request, $string, array $translationParams = [])
    {
        $string = $this['translator']->trans($string, $translationParams);
        $this->addFlash($request, 'error', $string);
    }

    public function addFlashNotice(Request $request, $string, array $translationParams = [])
    {
        $string = $this['translator']->trans($string, $translationParams);
        $this->addFlash($request, 'notice', $string);
    }
}
