<?php
namespace Pmi\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pmi\Entities\Configuration;
use Pmi\Security\UserProvider;
use Pmi\Audit\Log;

class HpoApplication extends AbstractApplication
{
    protected $configuration = [];
    protected $participantSource = 'rdr';

    public function setup($config = [])
    {
        parent::setup($config);

        $rdrOptions = [];
        if ($this->isLocal()) {
            $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/rdr_key.json';
            if (file_exists($keyFile)) {
                $rdrOptions['key_file'] = $keyFile;
            }
        }
        if ($this->getConfig('rdr_endpoint')) {
            $rdrOptions['endpoint'] = $this->getConfig('rdr_endpoint');
        }
        if ($this->getConfig('rdr_auth_json')) {
            $rdrOptions['key_contents'] = $this->getConfig('rdr_auth_json');
        }

        $this['pmi.drc.rdrhelper'] = new \Pmi\Drc\RdrHelper($rdrOptions);
        if ($this->participantSource == 'mock') {
            $this['pmi.drc.participants'] = new \Pmi\Drc\MockParticipantSearch();
        } else {
            $this['pmi.drc.participants'] = new \Pmi\Drc\RdrParticipants($this['pmi.drc.rdrhelper']);
        }

        $this['pmi.drc.appsclient'] = (!$this->isProd() && ($this['isUnitTest'] || $this->getConfig('gaBypass'))) ?
             \Pmi\Drc\MockAppsClient::createFromApp($this) : \Pmi\Drc\AppsClient::createFromApp($this);

        $this->registerDb();
        return $this;
    }
    
    protected function registerSecurity()
    {
        $this['app.googlegroups_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleGroupsAuthenticator($app);
        };
        
        // use an IP whitelist until GAE has built-in firewall rules
        $ips = $this->getIpWhitelist();
        if (count($ips) === 0) {
            // no config specified - allow everything ('::/0' doesn't work with IpUtils)
            $ips = ['0.0.0.0/0', '::/1'];
        }
        
        $app = $this;
        // include `/` in common routes because homeAction will redirect based on role
        $commonRegex = '^/(logout|login-return|keepalive|client-timeout|agree)?$';
        $anonRegex = '^/(timeout|login)$';
        $this->register(new \Silex\Provider\SecurityServiceProvider(), [
            'security.firewalls' => [
                'anonymous' => [
                    'pattern' => $anonRegex,
                    'anonymous' => true
                ],
                'main' => [
                    'pattern' => '^/.*$',
                    'guard' => [
                        'authenticators' => [
                            'app.googlegroups_authenticator'
                        ]
                    ],
                    'users' => function () use ($app) {
                        return new UserProvider($app);
                    }
                ]
            ],
            'security.access_rules' => [
                [['path' => $anonRegex, 'ips' => $ips], 'IS_AUTHENTICATED_ANONYMOUSLY'],
                [['path' => $anonRegex], 'ROLE_NO_ACCESS'],
                
                [['path' => '^/_dev/.*$', 'ips' => $ips], 'IS_AUTHENTICATED_FULLY'],
                [['path' => '^/_dev/.*$'], 'ROLE_NO_ACCESS'],
                
                [['path' => $commonRegex, 'ips' => $ips], 'IS_AUTHENTICATED_FULLY'],
                [['path' => $commonRegex], 'ROLE_NO_ACCESS'],
                
                [['path' => '^/dashboard/.*$', 'ips' => $ips], 'ROLE_DASHBOARD'],
                [['path' => '^/dashboard/.*$'], 'ROLE_NO_ACCESS'],

                [['path' => '^/.*$', 'ips' => $ips], 'ROLE_USER'],
                [['path' => '^/.*$'], 'ROLE_NO_ACCESS']
            ]
        ]);
    }

    protected function loadConfiguration($override = [])
    {
        // default two-factor setting
        $this->configuration['enforce2fa'] = $this->isProd();
        
        $appDir = realpath(__DIR__ . '/../../../');
        $configFile = $appDir . '/dev_config/config.yml';
        if ($this->isLocal() && file_exists($configFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $configs = $yaml->parse(file_get_contents($configFile));
            if (is_array($configs) || count($configs) > 0) {
                foreach ($configs as $key => $val) {
                    $this->configuration[$key] = $val;
                }
            }
        }

        // unit tests don't have access to Datastore
        if (!$this['isUnitTest']) {
            $configs = Configuration::fetchBy([]);
            foreach ($configs as $config) {
                $this->configuration[$config->getKey()] = $config->getValue();
            }
        }
        
        // load IP whitelist
        $whitelistFile = $appDir . '/ip_whitelist.yml';
        if (file_exists($whitelistFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $whitelistConfig = $yaml->parse(file_get_contents($whitelistFile));
            if (is_array($whitelistConfig['whitelist'])) {
                $this->configuration['ip_whitelist'] = implode(',', $whitelistConfig['whitelist']);
            }
        }
        
        foreach ($override as $key => $val) {
            $this->configuration[$key] = $val;
        }
    }
    
    protected function registerDb()
    {
        $socket = $this->getConfig('mysql_socket');
        $host = $this->getConfig('mysql_host');
        $schema = $this->getConfig('mysql_schema');
        $user = $this->getConfig('mysql_user');
        $password = $this->getConfig('mysql_password');
        if ($socket) {
            $options = [
                'driver' => 'pdo_mysql',
                'unix_socket' => $socket,
                'dbname' => $schema,
                'user' => $user,
                'password' => $password,
                'charset' => 'utf8mb4'
            ];
        } else {
            $options = [
                'driver' => 'pdo_mysql',
                'host' => $host,
                'dbname' => $schema,
                'user' => $user,
                'password' => $password,
                'charset' => 'utf8mb4'
            ];
        }
        $this->register(new \Silex\Provider\DoctrineServiceProvider(), [
            'db.options' => $options
        ]);

        $this['em'] = new \Pmi\EntityManager\EntityManager();
        $this['em']->setDbal($this['db']);
    }

    public function setHeaders(Response $response)
    {
        // prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // whitelist content that the client is allowed to request
        $whitelist =  "default-src 'self'"
            . " 'unsafe-eval'" // required for setTimeout and setInterval
            . " 'unsafe-inline'" // for the places we are using inline JS
            . " cdn.plot.ly;" // allow plot.ly remote requests
            . " img-src 'self' data:"; // allow self and data: urls for img src

        $response->headers->set('Content-Security-Policy', $whitelist);

        // prevent browsers from sending unencrypted requests
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }
    
    public function switchSite($email)
    {
        $user = $this->getUser();
        if ($user && $user->belongsToSite($email)) {
            $this['session']->set('site', $user->getSite($email));
            return true;
        } else {
            return false;
        }
    }
    
    /** Returns the user's currently selected HPO site. */
    public function getSite()
    {
        return $this['session']->get('site');
    }

    public function getSiteId()
    {
        if ($site = $this->getSite()) {
            return $site->id;
        } else {
            return null;
        }
    }
    
    protected function beforeCallback(Request $request, AbstractApplication $app)
    {
        $app->log(Log::REQUEST);
        
        // log the user out if their session is expired
        if ($this->isLoginExpired() && $request->attributes->get('_route') !== 'logout') {
            return $this->redirectToRoute('logout', ['timeout' => true]);
        }

        if ($this['session']->get('isLogin')) {
            $app->log(Log::LOGIN_SUCCESS, $this->getUser()->getRoles());
            $this->addFlashSuccess('Welcome, ' . $this->getUser()->getEmail() . '!');
        }

        // users with multiple roles must select their initial destination
        if ($this['session']->get('isLoginReturn') && $this->hasRole('ROLE_USER') && $this->hasRole('ROLE_DASHBOARD') && !$this->isUpkeepRoute($request)) {
            return $this->forwardToRoute('dashSplash', $request);
        }

        if($this->isLoggedIn()) {
            $user = $this->getUser();
            $this['em']->setTimezone($this->getUserTimezone());
        }

        // HPO users must select their site first
        if (!$this->getSite() && $this->isLoggedIn() && $this['security.authorization_checker']->isGranted('ROLE_USER'))
        {
            // auto-select since they only have one site
            if (count($user->getSites()) === 1) {
                $this->switchSite($user->getSites()[0]->email);
            } elseif ($request->attributes->get('_route') !== 'selectSite' &&
                    $request->attributes->get('_route') !== 'switchSite' &&
                    strpos($request->attributes->get('_route'), 'dashboard_') !== 0 &&
                    !$this->isUpkeepRoute($request)) {
                $request->request->set('destUrl', $request->getRequestUri());
                return $this->forwardToRoute('selectSite', $request);
            }
        }
    }

    protected function afterCallback(Request $request, Response $response)
    {
        $this->setHeaders($response);
    }
    
    protected function finishCallback(Request $request, Response $response)
    {
        if ($this->isLoggedIn()) {
            // only the first route handled is considered a login
            $this['session']->set('isLogin', false);
            
            // unset after the first route handled following loginReturn
            if (!$this->isUpkeepRoute($request)) {
                $this['session']->set('isLoginReturn', false);
            }
        }
    }
}
