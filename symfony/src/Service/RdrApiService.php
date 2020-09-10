<?php

namespace App\Service;

use App\Service;
use Pmi\Entities\Configuration as DatastoreConfiguration;
use Google_Client as GoogleClient;
use Google_Service_Oauth2 as GoogleServiceOauth2;
use Pmi\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class RdrApiService
{
    protected $googleClient;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $config = [];
    protected $cache;

    public function __construct(EnvironmentService $environment, KernelInterface $appKernel, GoogleClient $googleClient)
    {
        $datastore = new DatastoreConfiguration();
        $this->googleClient = $googleClient;
        $basePath = $appKernel->getProjectDir();
        // Note that when installed in ./symfony, the development credentials are a level down
        if ($environment->isLocal() && file_exists($basePath . '/../dev_config/rdr_key.json')) {
            $this->config['key_file'] = $basePath . '/../dev_config/rdr_key.json';
        }
        // Load endpoint from configuration
        if ($datastore->fetchOneById('rdr_endpoint')) {
            $this->endpoint = $datastore->fetchOneById('rdr_endpoint');
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
        if (isset($this->config['key_file']) && !empty($this->config['key_file'])) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->config['key_file']);
        }
        $this->googleClient->useApplicationDefaultCredentials();

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
