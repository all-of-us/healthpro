<?php
namespace Pmi\Application;

use Symfony\Component\HttpFoundation\Response;

class HpoApplication extends AbstractApplication
{
    public function setup()
    {
        parent::setup();

        $this['pmi.drc.participantsearch'] = new \Pmi\Drc\ParticipantSearch();
        $this['pmi.drc.appsclient'] = new \Pmi\Drc\AppsClient($this);

        $app = $this;
        
        $this['app.googleapps_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleAppsAuthenticator($app);
        };
        $this['app.googlegroups_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleGroupsAuthenticator($app);
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
                ],
                'googlegroups' => [
                    'pattern' => '^/googlegroups',
                    'stateless' => true, // because Google handles auth state
                    'guard' => [
                        'authenticators' => [
                            'app.googlegroups_authenticator'
                        ]
                    ]
                ]
            ]
        ]);
        
        return $this;
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
