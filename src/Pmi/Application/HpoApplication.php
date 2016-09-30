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
        if ($this->isDev()) {
            $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/rdr_key.json';
            if (file_exists($keyFile)) {
                $rdrOptions['key_file'] = $keyFile;
            }
            if ($this->getConfig('rdr_endpoint')) {
                $rdrOptions['endpoint'] = $this->getConfig('rdr_endpoint');
            }
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

        $this['pmi.drc.appsclient'] = $this['isUnitTest'] ?
            new \Tests\Pmi\Drc\AppsClient() : \Pmi\Drc\AppsClient::createFromApp($this);

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
                
                [['path' => '^/dashboard/.*$', 'ips' => $ips], 'ROLE_DASHBOARD'],
                [['path' => '^/dashboard/.*$'], 'ROLE_NO_ACCESS'],

                [['path' => '^/.*$', 'ips' => $ips], 'ROLE_USER'],
                [['path' => '^/.*$'], 'ROLE_NO_ACCESS']
            ]
        ]);
    }

    protected function loadConfiguration($override = [])
    {
        $appDir = realpath(__DIR__ . '/../../../');
        $configFile = $appDir . '/dev_config/config.yml';
        if ($this->isDev() && file_exists($configFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $config = $yaml->parse(file_get_contents($configFile));
            if (is_array($config) || count($config) > 0) {
                $this->configuration = $config;
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
    }

    public function setHeaders(Response $response)
    {
        // prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // whitelist content that the client is allowed to request
        $whitelist =  "default-src 'self'"
            . " 'unsafe-inline'" // for the places we are using inline JS
            . ' *.googleapis.com'
            . ' *.gstatic.com'
            . ' *.google-analytics.com'
            . ' *.google.com' // reCAPTCHA
            . ' *.youtube.com';

        $response->headers->set('Content-Security-Policy', $whitelist);

        // prevent browsers from sending unencrypted requests
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }
    
    protected function beforeCallback(Request $request, AbstractApplication $app)
    {
        // log the user out if their session is expired
        if ($this->isLoginExpired() && $request->attributes->get('_route') !== 'logout') {
            return $this->redirectToRoute('logout', ['timeout' => true]);
        }
        
        if ($this['session']->get('isLogin')) {
            $app->log(Log::LOGIN_SUCCESS, $this->getUser()->getRoles());
            $this->addFlashSuccess('Login successful, welcome ' . $this->getUser()->getEmail() . '!');
        } else {
            $app->log(Log::REQUEST);
        }
    }
    
    protected function finishCallback(Request $request, Response $response)
    {
        // only the first request handled is considered a login
        if ($this['security.token_storage']->getToken() && $this['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this['session']->set('isLogin', false);
        }
    }
}
