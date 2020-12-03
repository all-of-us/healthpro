<?php

namespace App\Service;

use Google_Client as GoogleClient;
use Google_Service_Oauth2 as GoogleServiceOauth2;
use Psr\Log\LoggerInterface;
use Pmi\Cache\DatastoreAdapter;
use Pmi\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class RdrApiService
{
    protected $googleClient;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $config = [];
    protected $cache;
    protected $logger;

    public function __construct(EnvironmentService $environment, KernelInterface $appKernel, GoogleClient $googleClient, ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->googleClient = $googleClient;
        $basePath = $appKernel->getProjectDir();
        // Note that when installed in ./symfony, the development credentials are a level down
        if ($environment->isLocal() && file_exists($basePath . '/../dev_config/rdr_key.json')) {
            $this->config['key_file'] = $basePath . '/../dev_config/rdr_key.json';
        }
        if ($params->has('rdr_auth_json')) {
            $this->config['rdr_auth_json'] = $params->get('rdr_auth_json');
        }
        // Load endpoint from configuration
        if ($params->has('rdr_endpoint')) {
            $this->endpoint = $params->get('rdr_endpoint');
        }
        // Set up OAuth Cache
        if (!$params->has('rdr_auth_cache_disabled')) {
            $this->logger = $logger;
            $this->cache = new DatastoreAdapter($params->get('ds_clean_up_limit'));
            $this->cache->setLogger($this->logger);
        }
    }

    public function get($path, $params = [])
    {
        return $this->getClient($path)->request('GET', $this->endpoint . $path, $params);
    }

    public function post($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->getClient($path)->request('POST', $this->endpoint . $path, $params);
    }

    /* Private Methods */

    private function getClient($resourceEndpoint = null)
    {
        if (!empty($this->config['rdr_auth_json'])) {
            $this->googleClient->setAuthConfig(json_decode($this->config['rdr_auth_json'], true));
        } else {
            if (!empty($this->config['key_file'])) {
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->config['key_file']);
            }
            $this->googleClient->useApplicationDefaultCredentials();
        }

        $this->googleClient->addScope(GoogleServiceOauth2::USERINFO_EMAIL);

        if ($resourceEndpoint) {
            $endpoint = $this->endpoint . $resourceEndpoint;
        } else {
            $endpoint = $this->endpoint;
        }

        if ($this->cache) {
            $this->googleClient->setCache($this->cache);
        }

        return $this->googleClient->authorize(new HttpClient([
            'base_uri' => $endpoint,
            'timeout' => 50
        ]));
    }
}
