<?php
namespace Pmi\Application;

use Symfony\Component\HttpFoundation\Response;
use Pmi\Entities\Configuration;

class HpoApplication extends AbstractApplication
{
    protected $configuration = [];

    public function setup()
    {
        parent::setup();

        $this->loadConfiguration();
        $this['pmi.drc.participantsearch'] = new \Pmi\Drc\ParticipantSearch();

        $app = $this;
        
        $this['app.googleapps_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleAppsAuthenticator($app);
        };
        
        $this->register(new \Silex\Provider\SecurityServiceProvider(), [
            'security.firewalls' => [
                'googleapps' => [
                    'pattern' => '^/googleapps',
                    'stateless' => true, // because Google handles auth state
                    'guard' => [
                        'authenticators' => [
                            'app.googleapps_authenticator'
                        ]
                    ]
                ]
            ]
        ]);
        
        return $this;
    }

    public function loadConfiguration()
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
