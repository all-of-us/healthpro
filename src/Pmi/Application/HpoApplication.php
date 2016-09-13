<?php
namespace Pmi\Application;

use Symfony\Component\HttpFoundation\Response;
use Pmi\Entities\Configuration;
use Pmi\Security\UserProvider;

class HpoApplication extends AbstractApplication
{
    protected $configuration = [];

    public function setup()
    {
        parent::setup();

        $this->loadConfiguration();
        $this['pmi.drc.participantsearch'] = new \Pmi\Drc\ParticipantSearch();
        $this['pmi.drc.appsclient'] = new \Pmi\Drc\AppsClient($this);

        $this->registerDb();
        return $this;
    }
    
    protected function registerSecurity()
    {
        $this['app.googlegroups_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleGroupsAuthenticator($app);
        };
        
        $app = $this;
        $this->register(new \Silex\Provider\SecurityServiceProvider(), [
            'security.firewalls' => [
                'main' => [
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
                ['^/logout$', 'IS_AUTHENTICATED_ANONYMOUSLY'],
                ['^/.*$', 'ROLE_USER']
            ]
        ]);
    }

    protected function loadConfiguration()
    {
        if ($this['isUnitTest']) {
            return;
        }
        $configs = Configuration::fetchBy([]);
        foreach ($configs as $config) {
            $this->configuration[$config->getKey()] = $config->getValue();
        }
    }

    public function getConfig($key)
    {
        if (isset($this->configuration[$key])) {
            return $this->configuration[$key];
        } else {
            return null;
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
}
